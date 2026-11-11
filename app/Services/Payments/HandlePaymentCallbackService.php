<?php

namespace App\Services\Payments;

use App\Jobs\SendWabotBookingConfirmation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\WabotMessageLog;
use App\Services\Orders\ActivateCustomerDashboardService;
use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\AdvancePaidConfirmedOrderToDesignService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HandlePaymentCallbackService
{
    public function __construct(
        private SynchronizeOrderFinancialsService $financials,
        private ActivateCustomerDashboardService $dashboard,
        private BuildFinalReviewService $finalReview,
        private ConfirmOrderDetailsService $confirmOrder,
        private BuildOrderPricingService $pricing,
        private AdvancePaidConfirmedOrderToDesignService $designReadiness,
        private InitializePrintJobsForOrderService $initializePrintJobs,
        private SyncOrderPrintStatusService $syncPrintStatus,
    ) {}

    public function handle(array $verified): PaymentTransaction
    {
        $payment = DB::transaction(function () use ($verified) {
            foreach (['provider', 'provider_reference', 'status'] as $required) {
                if (! array_key_exists($required, $verified)) {
                    throw new RuntimeException("Missing verified callback field: {$required}");
                }
            }

            $payment = PaymentTransaction::where('provider', $verified['provider'])
                ->where('provider_reference', $verified['provider_reference'])
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('amount_sen', $verified)
                && (int) $verified['amount_sen'] !== $this->toSen((string) $payment->amount)) {
                throw new RuntimeException('Billplz callback amount does not match the payment ledger.');
            }

            $eventId = $verified['provider_event_id'] ?? null;

            if ($eventId) {
                $existingEvent = $payment->events()
                    ->where('provider_event_id', $eventId)
                    ->first();

                if ($existingEvent) {
                    return $payment->fresh();
                }
            }

            $payment->events()->create([
                'event_type' => 'PROVIDER_CALLBACK',
                'provider_event_id' => $eventId,
                'payload' => $verified['raw_payload'] ?? $verified,
                'occurred_at' => now(),
            ]);

            if ($verified['status'] === 'PAID') {
                $becamePaid = $payment->status !== 'PAID';
                $transactionId = $verified['raw_payload']['transaction_id'] ?? null;
                $metadata = $payment->metadata ?? [];
                if (is_scalar($transactionId) && trim((string) $transactionId) !== '') {
                    $metadata['billplz']['transaction_id'] = trim((string) $transactionId);
                }

                if ($payment->status !== 'PAID') {
                    $payment->update([
                        'status' => 'PAID',
                        'paid_at' => $this->normalizePaidAt($verified['paid_at'] ?? null),
                        'metadata' => $metadata,
                    ]);
                } elseif ($metadata !== ($payment->metadata ?? [])) {
                    $payment->update(['metadata' => $metadata]);
                }

                if ($payment->payment_type === 'BALANCE') {
                    $order = $payment->order()
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $order->isTerminalOperationalStatus()) {
                        $order->update([
                            'status' => 'PAID',
                        ]);
                    }
                    $this->financials->synchronize($order);
                } elseif ($payment->payment_type === 'BOOKING_DEPOSIT') {
                    $order = $payment->order()
                        ->lockForUpdate()
                        ->firstOrFail();
                    $order->update([
                        'booking_payment_status' => 'PAID',
                        'wabot_active_booking_key' => null,
                    ]);
                    $this->dashboard->activate($order, $payment);
                    $this->financials->synchronize($order);

                    if ($becamePaid && $order->source === 'WABOT') {
                        $messageLog = WabotMessageLog::query()->firstOrCreate(
                            ['payment_transaction_id' => $payment->id, 'message_type' => 'BOOKING_CONFIRMED'],
                            ['order_id' => $order->id, 'phone' => (string) $order->customer_phone, 'status' => 'PENDING'],
                        );
                        SendWabotBookingConfirmation::dispatch($messageLog->id)
                            ->onConnection('database')
                            ->afterCommit();
                    }
                }
            } elseif ($verified['status'] === 'FAILED') {
                if ($payment->status !== 'PAID') {
                    $payment->update([
                        'status' => 'FAILED',
                    ]);
                }
            } elseif ($verified['status'] === 'CANCELLED' && $payment->status !== 'PAID') {
                $payment->update(['status' => 'CANCELLED']);
                if ($payment->payment_type === 'BOOKING_DEPOSIT') {
                    $payment->order()->update(['wabot_active_booking_key' => null]);
                }
            }

            return $payment->fresh();
        });

        if ($payment->payment_type === 'BOOKING_DEPOSIT' && $payment->status === 'PAID') {
            try {
                $this->confirmFullTestOrderAfterPayment($payment);
            } catch (Throwable $exception) {
                report($exception);
                Log::warning('Full test order payment could not confirm the order details.', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
            }

            try {
                $this->designReadiness->advance(
                    $payment->order()->firstOrFail(),
                    eventType: $this->isFullTestOrder($payment) ? 'FULL_TEST_PAYMENT_CONFIRMED' : 'BOOKING_PAYMENT_CONFIRMED',
                    metadata: ['payment_transaction_id' => $payment->id],
                );
            } catch (Throwable $exception) {
                report($exception);
                Log::warning('Paid order could not be advanced to the design queue.', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
            }
        }

        if ($payment->payment_type === 'BALANCE' && $payment->status === 'PAID') {
            $order = $payment->order()->firstOrFail();

            if ($order->status === 'PAID'
                && $order->designJobs()->count() === (int) $order->package_count
                && $order->designJobs()->where('status', '<>', 'DESIGN_APPROVED')->doesntExist()) {
                try {
                    $this->initializePrintJobs->initialize($order);
                    $this->syncPrintStatus->sync($order->fresh());
                } catch (Throwable $exception) {
                    report($exception);
                    Log::warning('Paid order could not be advanced to the printing queue.', [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                    ]);
                }
            }
        }

        return $payment->fresh();
    }

    private function confirmFullTestOrderAfterPayment(PaymentTransaction $payment): void
    {
        $order = $payment->order()->firstOrFail();
        $acknowledgements = data_get($payment->metadata, 'confirmation_acknowledgements', []);

        if (! $this->isFullTestOrder($payment)
            || $order->status !== 'DETAILS_INCOMPLETE'
            || ! (bool) ($acknowledgements['responsibility_acknowledged'] ?? false)
            || ! (bool) ($acknowledgements['post_confirmation_liability_acknowledged'] ?? false)) {
            return;
        }

        $livePreview = $this->finalReview->build($order);

        DB::transaction(function () use ($order, $livePreview): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== 'DETAILS_INCOMPLETE') {
                return;
            }

            $confirmedOrder = $this->confirmOrder->confirm($lockedOrder, $livePreview);
            $this->pricing->createSnapshot($confirmedOrder);
            $confirmation = $confirmedOrder->confirmation;
            $confirmation->update([
                'confirmed_snapshot' => array_merge($confirmation->confirmed_snapshot ?? [], [
                    'responsibility_acknowledged' => true,
                    'post_confirmation_liability_acknowledged' => true,
                ]),
            ]);
            $this->financials->synchronize($confirmedOrder);
        }, 3);
    }

    private function isFullTestOrder(PaymentTransaction $payment): bool
    {
        $order = $payment->order;

        return $payment->payment_type === 'BOOKING_DEPOSIT'
            && $order?->source === 'TEST'
            && $order?->payment_plan === 'FULL';
    }

    private function normalizePaidAt(mixed $paidAt): CarbonImmutable
    {
        if (! is_string($paidAt) || trim($paidAt) === '') {
            return CarbonImmutable::now('UTC');
        }

        try {
            return CarbonImmutable::parse($paidAt, (string) config('app.display_timezone'))->utc();
        } catch (InvalidFormatException) {
            return CarbonImmutable::now('UTC');
        }
    }

    private function toSen(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\\d*)\\.([0-9]{2})$/', $amount, $matches)) {
            throw new RuntimeException('Payment ledger amount must have exactly two decimal places.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }
}
