<?php

namespace App\Console\Commands;

use App\Models\CustomerEmailNotification;
use App\Models\Order;
use App\Services\Orders\CustomerOrderEmailNotificationService;
use App\Services\Orders\ValidateOrderCompletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('customers:send-order-reminders')]
#[Description('Queue customer reminders for incomplete details and unpaid balances')]
class SendCustomerOrderReminders extends Command
{
    public function handle(
        ValidateOrderCompletionService $completionValidator,
        CustomerOrderEmailNotificationService $notifications,
    ): int {
        if (! (bool) config('customer_reminders.enabled')) {
            $this->info('Customer email reminders are disabled.');

            return self::SUCCESS;
        }

        if (! $notifications->isReady()) {
            $this->error('Customer email reminders are enabled but the email sender is not configured.');

            return self::FAILURE;
        }

        $detailsCount = 0;
        Order::query()
            ->where('status', 'DETAILS_INCOMPLETE')
            ->where('booking_payment_status', 'PAID')
            ->whereNotNull('customer_email')
            ->where('customer_email', '<>', '')
            ->whereNotNull('dashboard_activated_at')
            ->where('dashboard_activated_at', '<=', now()->subHours((int) config('customer_reminders.details_after_hours', 24)))
            ->with(['couples', 'packageSides.design', 'packageSides.parents', 'packageSides.event.contacts', 'fulfilment'])
            ->lazyById(100)
            ->each(function (Order $order) use ($completionValidator, $notifications, &$detailsCount): void {
                if ($completionValidator->validate($order)['complete']) {
                    return;
                }

                if ($this->hasReminder($order, CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE)) {
                    return;
                }

                if ($notifications->queueDetailsReminder($order)) {
                    $detailsCount++;
                }
            });

        $balanceCount = 0;
        Order::query()
            ->whereIn('status', ['DESIGN_APPROVED', 'BALANCE_PENDING'])
            ->whereNotNull('customer_email')
            ->where('customer_email', '<>', '')
            ->where('updated_at', '<=', now()->subHours((int) config('customer_reminders.balance_after_hours', 72)))
            ->with('pricingSnapshot')
            ->lazyById(100)
            ->each(function (Order $order) use ($notifications, &$balanceCount): void {
                if ((float) ($order->pricingSnapshot?->outstanding_amount ?? 0) <= 0) {
                    return;
                }

                if ($this->hasReminder($order, CustomerEmailNotification::TYPE_BALANCE_DUE)) {
                    return;
                }

                if ($notifications->queueBalanceReminder($order)) {
                    $balanceCount++;
                }
            });

        $this->info("Queued {$detailsCount} incomplete-details reminder(s) and {$balanceCount} balance reminder(s).");

        return self::SUCCESS;
    }

    private function hasReminder(Order $order, string $eventType): bool
    {
        return CustomerEmailNotification::query()
            ->where('order_id', $order->id)
            ->where('event_type', $eventType)
            ->whereIn('status', [
                CustomerEmailNotification::STATUS_PENDING,
                CustomerEmailNotification::STATUS_SENT,
                CustomerEmailNotification::STATUS_SKIPPED,
            ])
            ->exists();
    }
}
