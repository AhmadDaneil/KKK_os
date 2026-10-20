<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\Payments\BillplzPaymentException;
use App\Services\Payments\BillplzPaymentGateway;
use App\Services\Payments\HandlePaymentCallbackService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('payments:reconcile-pending-billplz')]
#[Description('Reconcile pending Billplz payments against the authenticated provider API')]
class ReconcilePendingBillplzPayments extends Command
{
    private const BATCH_SIZE = 25;

    public function handle(
        BillplzPaymentGateway $gateway,
        HandlePaymentCallbackService $handler,
    ): int {
        if (! config('services.billplz.enabled')) {
            $this->info('Billplz is disabled; no pending payments were reconciled.');

            return self::SUCCESS;
        }

        $recentCheckCutoff = now()->subSeconds(55)->utc()->toIso8601String();
        $payments = PaymentTransaction::query()
            ->where('provider', 'BILLPLZ')
            ->where('status', 'PENDING')
            ->whereIn('payment_type', ['BOOKING_DEPOSIT', 'BALANCE'])
            ->whereNotNull('provider_reference')
            ->where('provider_reference', '<>', '')
            ->where(function ($query) use ($recentCheckCutoff): void {
                $query->whereNull('metadata->billplz->last_status_checked_at')
                    ->orWhere('metadata->billplz->last_status_checked_at', '<', $recentCheckCutoff);
            })
            ->orderBy('metadata->billplz->last_status_checked_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        $checked = 0;
        $resolved = 0;
        $failures = 0;

        foreach ($payments as $payment) {
            try {
                $verified = $gateway->retrievePaymentStatus($payment);
                $this->recordLastStatusCheck($payment);

                $updatedPayment = $handler->handle($verified);
                $checked++;
                if ($updatedPayment->status !== 'PENDING') {
                    $resolved++;
                }
                $this->line("Payment {$updatedPayment->id}: {$updatedPayment->status}");
            } catch (Throwable $exception) {
                $reason = $exception instanceof BillplzPaymentException
                    ? $exception->reason
                    : 'unexpected_error';

                try {
                    $this->recordLastStatusCheck($payment);
                } catch (Throwable $metadataException) {
                    report($metadataException);
                }

                Log::warning('Scheduled Billplz payment reconciliation failed.', [
                    'payment_id' => $payment->id,
                    'reason' => $reason,
                ]);
                $this->warn("Payment {$payment->id}: reconciliation failed ({$reason}).");
                $failures++;
            }
        }

        $this->info("Successfully checked {$checked} of {$payments->count()} pending Billplz payment(s); {$resolved} resolved, {$failures} failed.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function recordLastStatusCheck(PaymentTransaction $payment): void
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $billplzMetadata = is_array($metadata['billplz'] ?? null) ? $metadata['billplz'] : [];
        $billplzMetadata['last_status_checked_at'] = now()->utc()->toIso8601String();
        $metadata['billplz'] = $billplzMetadata;

        $payment->update(['metadata' => $metadata]);
    }
}
