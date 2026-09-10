<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HandlePaymentCallbackService
{
    public function handle(array $verified): PaymentTransaction
    {
        return DB::transaction(function () use ($verified) {
            foreach (['provider', 'provider_reference', 'status'] as $required) {
                if (! array_key_exists($required, $verified)) {
                    throw new RuntimeException("Missing verified callback field: {$required}");
                }
            }

            $payment = PaymentTransaction::where('provider', $verified['provider'])
                ->where('provider_reference', $verified['provider_reference'])
                ->lockForUpdate()
                ->firstOrFail();

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
                if ($payment->status !== 'PAID') {
                    $payment->update([
                        'status' => 'PAID',
                        'paid_at' => now(),
                    ]);
                }

                if ($payment->payment_type === 'BALANCE') {
                    $payment->order()->update([
                        'status' => 'PAID',
                    ]);
                }
            } elseif ($verified['status'] === 'FAILED') {
                if ($payment->status !== 'PAID') {
                    $payment->update([
                        'status' => 'FAILED',
                    ]);
                }
            }

            return $payment->fresh();
        });
    }
}
