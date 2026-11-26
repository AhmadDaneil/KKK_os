<?php

namespace App\Services\Orders;

use App\Jobs\SendCustomerEmailNotification;
use App\Mail\CustomerOrderNoticeMail;
use App\Models\CustomerEmailNotification;
use App\Models\Order;
use App\Models\OrderProgressUpdate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class CustomerOrderEmailNotificationService
{
    private const SUPPRESSED_STATUS_NOTICES = [
        'BOOKING_PENDING',
        'BOOKED',
        'DETAILS_INCOMPLETE',
        'DETAILS_CONFIRMED',
        'ARCHIVED',
    ];

    public function isReady(): bool
    {
        if (! (bool) config('customer_reminders.enabled') || ! filled(config('customer_reminders.from_address'))) {
            return false;
        }

        $mailer = (string) config('customer_reminders.mailer');

        if (config("mail.mailers.{$mailer}.transport") === 'gmail-api') {
            return filled(config("mail.mailers.{$mailer}.client_id"))
                && filled(config("mail.mailers.{$mailer}.client_secret"))
                && filled(config("mail.mailers.{$mailer}.refresh_token"));
        }

        return filled(config("mail.mailers.{$mailer}.username"))
            && filled(config("mail.mailers.{$mailer}.password"));
    }

    public function queueDetailsReminder(Order $order): bool
    {
        return $this->queue($order, CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE, 'details|'.$order->id);
    }

    public function queueBalanceReminder(Order $order): bool
    {
        return $this->queue($order, CustomerEmailNotification::TYPE_BALANCE_DUE, 'balance|'.$order->id);
    }

    public function queueStatusUpdate(Order $order, string $status): bool
    {
        if (in_array($status, self::SUPPRESSED_STATUS_NOTICES, true)) {
            return false;
        }

        $occurredAt = $order->updated_at?->format('Y-m-d H:i:s.u') ?? now()->format('Y-m-d H:i:s.u');

        return $this->queue(
            $order,
            CustomerEmailNotification::TYPE_ORDER_STATUS,
            "status|{$order->id}|{$status}|{$occurredAt}",
            statusTo: $status,
        );
    }

    public function queueProgressUpdate(Order $order, OrderProgressUpdate $progressUpdate): bool
    {
        if (! $progressUpdate->isCustomerVisible() || (int) $progressUpdate->order_id !== (int) $order->id) {
            return false;
        }

        return $this->queue(
            $order,
            CustomerEmailNotification::TYPE_PROGRESS_UPDATE,
            'progress|'.$progressUpdate->id,
            progressUpdateId: $progressUpdate->id,
        );
    }

    public function send(int $notificationId, ValidateOrderCompletionService $completionValidator): void
    {
        $notification = CustomerEmailNotification::query()
            ->with(['order.pricingSnapshot', 'progressUpdate'])
            ->find($notificationId);

        if (! $notification || in_array($notification->status, [
            CustomerEmailNotification::STATUS_SENT,
            CustomerEmailNotification::STATUS_SKIPPED,
        ], true)) {
            return;
        }

        if (! $this->isReady()) {
            throw new RuntimeException('Customer email sender is not configured.');
        }

        $order = $notification->order;

        if (! filled($order->customer_email)) {
            $this->skip($notification, 'CUSTOMER_EMAIL_MISSING');

            return;
        }

        if ($notification->event_type === CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE
            && ($order->status !== 'DETAILS_INCOMPLETE' || $completionValidator->validate($order)['complete'])) {
            $this->skip($notification, 'DETAILS_NO_LONGER_INCOMPLETE');

            return;
        }

        if ($notification->event_type === CustomerEmailNotification::TYPE_BALANCE_DUE
            && (! in_array($order->status, ['DESIGN_APPROVED', 'BALANCE_PENDING'], true)
                || (float) ($order->pricingSnapshot?->outstanding_amount ?? 0) <= 0)) {
            $this->skip($notification, 'BALANCE_NO_LONGER_DUE');

            return;
        }

        if ($notification->event_type === CustomerEmailNotification::TYPE_PROGRESS_UPDATE
            && (! $notification->progressUpdate?->isCustomerVisible()
                || (int) $notification->progressUpdate->order_id !== (int) $order->id)) {
            $this->skip($notification, 'PROGRESS_NO_LONGER_VISIBLE');

            return;
        }

        $notice = $this->buildNotice($notification);
        $notification->forceFill([
            'attempts' => $notification->attempts + 1,
            'error_code' => null,
        ])->save();

        Mail::mailer((string) config('customer_reminders.mailer'))
            ->to($order->customer_email)
            ->send(new CustomerOrderNoticeMail($notice['subject'], $notice['content']));

        $notification->forceFill([
            'status' => CustomerEmailNotification::STATUS_SENT,
            'sent_at' => now(),
            'error_code' => null,
        ])->save();
    }

    private function queue(
        Order $order,
        string $eventType,
        string $eventIdentity,
        ?int $progressUpdateId = null,
        ?string $statusTo = null,
    ): bool {
        if (! $this->isReady() || ! filled($order->customer_email)) {
            return false;
        }

        $notification = CustomerEmailNotification::query()->firstOrCreate(
            ['event_key' => hash('sha256', $eventIdentity)],
            [
                'order_id' => $order->id,
                'order_progress_update_id' => $progressUpdateId,
                'event_type' => $eventType,
                'status_to' => $statusTo,
                'status' => CustomerEmailNotification::STATUS_PENDING,
            ],
        );

        if ($notification->status === CustomerEmailNotification::STATUS_SENT
            || $notification->status === CustomerEmailNotification::STATUS_SKIPPED) {
            return false;
        }

        $isRetry = $notification->status === CustomerEmailNotification::STATUS_FAILED;

        if ($isRetry) {
            $notification->forceFill([
                'status' => CustomerEmailNotification::STATUS_PENDING,
                'error_code' => null,
            ])->save();
        }

        SendCustomerEmailNotification::dispatch($notification->id)->afterCommit();

        return $notification->wasRecentlyCreated || $isRetry;
    }

    /**
     * @return array{subject: string, content: array{customerName: string, orderReference: string, title: string, bodyText: string, statusLabel: ?string, amount: ?string, actionUrl: string, actionLabel: string}}
     */
    private function buildNotice(CustomerEmailNotification $notification): array
    {
        $order = $notification->order;
        $reference = (string) ($order->displayCustomerReference() ?: $order->order_id);
        $customerName = (string) ($order->customer_name ?: 'Pelanggan');
        $actionUrl = route('orders.access.create', ['order_id' => $order->order_id]);
        $statusLabel = null;
        $amount = null;
        $actionLabel = 'Buka Tempahan';

        [$title, $message, $subject] = match ($notification->event_type) {
            CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE => [
                'Maklumat tempahan belum lengkap',
                'Maklumat tempahan anda masih belum lengkap. Sila lengkapkan maklumat yang diperlukan supaya pasukan kami dapat meneruskan proses design.',
                "Lengkapkan maklumat tempahan {$reference}",
            ],
            CustomerEmailNotification::TYPE_BALANCE_DUE => [
                'Peringatan bayaran baki',
                'Design tempahan anda telah diluluskan dan bayaran baki diperlukan sebelum proses cetakan. Sila semak tempahan untuk melihat jumlah baki dan pilihan bayaran.',
                "Peringatan bayaran baki tempahan {$reference}",
            ],
            CustomerEmailNotification::TYPE_ORDER_STATUS => $this->statusCopy((string) $notification->status_to, $reference),
            CustomerEmailNotification::TYPE_PROGRESS_UPDATE => [
                'Kemas kini tempahan anda',
                $this->progressUpdateMessage($notification->progressUpdate),
                "Kemas kini tempahan {$reference}",
            ],
            default => [
                'Kemas kini tempahan anda',
                'Terdapat kemas kini untuk tempahan anda. Sila buka tempahan untuk maklumat lanjut.',
                "Kemas kini tempahan {$reference}",
            ],
        };

        if ($notification->event_type === CustomerEmailNotification::TYPE_BALANCE_DUE) {
            $amount = 'RM '.number_format((float) $order->pricingSnapshot?->outstanding_amount, 2);
            $actionLabel = 'Semak dan Bayar Baki';
        } elseif ($notification->event_type === CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE) {
            $actionLabel = 'Lengkapkan Maklumat';
        } elseif ($notification->event_type === CustomerEmailNotification::TYPE_ORDER_STATUS) {
            $statusCopy = Lang::get('ui.progress.'.(string) $notification->status_to, [], 'ms');
            $statusLabel = is_array($statusCopy) && isset($statusCopy[0])
                ? (string) $statusCopy[0]
                : (string) $notification->status_to;
        }

        return [
            'subject' => $subject,
            'content' => [
                'customerName' => $customerName,
                'orderReference' => $reference,
                'title' => $title,
                'bodyText' => $message,
                'statusLabel' => $statusLabel,
                'amount' => $amount,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel,
            ],
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function statusCopy(string $status, string $reference): array
    {
        if ($status === 'DESIGN_IN_PROGRESS') {
            return [
                'Design sedang diproses',
                'Pasukan design kami sedang menyiapkan tempahan anda.',
                "Progress tempahan {$reference} dikemas kini",
            ];
        }

        $copy = Lang::get('ui.progress.'.$status, [], 'ms');
        $copy = is_array($copy) && count($copy) >= 2
            ? $copy
            : Lang::get('ui.progress.DEFAULT', [], 'ms');

        return [
            (string) $copy[0],
            (string) $copy[1],
            "Progress tempahan {$reference} dikemas kini",
        ];
    }

    private function progressUpdateMessage(?OrderProgressUpdate $progressUpdate): string
    {
        if (! $progressUpdate || ! filled($progressUpdate->message)) {
            return 'Pasukan kami telah menambah kemas kini baharu untuk tempahan anda. Sila buka tempahan untuk melihat maklumat lanjut.';
        }

        return trim((string) $progressUpdate->message);
    }

    private function skip(CustomerEmailNotification $notification, string $reason): void
    {
        $notification->forceFill([
            'status' => CustomerEmailNotification::STATUS_SKIPPED,
            'error_code' => $reason,
        ])->save();
    }
}
