<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBillplzBalancePaymentService
{
    public function __construct(private BillplzPaymentGateway $gateway) {}

    public function create(Order $order): string
    {
        if (! config('services.billplz.enabled')) {
            throw ValidationException::withMessages([
                'billplz' => 'Billplz payments are not enabled.',
            ]);
        }

        $checkout = DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, ['DESIGN_APPROVED', 'BALANCE_PENDING'], true)) {
                throw ValidationException::withMessages([
                    'billplz' => 'The balance can only be paid after the artwork is approved.',
                ]);
            }

            $snapshot = $lockedOrder->pricingSnapshot()->lockForUpdate()->first();
            if ($snapshot === null || $snapshot->currency !== 'MYR') {
                throw ValidationException::withMessages([
                    'billplz' => 'The order price snapshot is unavailable for the balance payment.',
                ]);
            }

            $amount = (string) $snapshot->outstanding_amount;
            if ($this->toSen($amount) <= 0) {
                throw ValidationException::withMessages([
                    'billplz' => 'There is no remaining balance for this order.',
                ]);
            }

            $existing = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->where('payment_type', 'BALANCE')
                ->whereIn('status', ['PENDING', 'PAID'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                if ($existing->status === 'PAID') {
                    throw ValidationException::withMessages([
                        'billplz' => 'The balance payment for this order has already been confirmed.',
                    ]);
                }

                if ($existing->provider !== 'BILLPLZ') {
                    throw ValidationException::withMessages([
                        'billplz' => 'An existing balance payment is awaiting review. Please use that transaction.',
                    ]);
                }

                $paymentUrl = $existing->metadata['billplz']['checkout_url'] ?? null;
                if (is_string($paymentUrl) && $paymentUrl !== '') {
                    return [
                        'payment' => $existing,
                        'payment_url' => $paymentUrl,
                        'should_create_bill' => false,
                    ];
                }

                throw ValidationException::withMessages([
                    'billplz' => 'The bill is being prepared. Please resume shortly.',
                ]);
            }

            $payment = $lockedOrder->payments()->create([
                'payment_type' => 'BALANCE',
                'provider' => 'BILLPLZ',
                'amount' => $amount,
                'currency' => $snapshot->currency,
                'status' => 'PENDING',
                'metadata' => [
                    'billplz' => [
                        'checkout_status' => 'CREATING',
                    ],
                ],
            ]);

            $payment->events()->create([
                'event_type' => 'BALANCE_PAYMENT_CREATED',
                'payload' => [
                    'provider' => 'BILLPLZ',
                    'amount' => $amount,
                    'currency' => $snapshot->currency,
                ],
                'occurred_at' => now(),
            ]);

            $lockedOrder->update(['status' => 'BALANCE_PENDING']);

            return [
                'payment' => $payment,
                'payment_url' => null,
                'should_create_bill' => true,
            ];
        }, 3);

        if (! $checkout['should_create_bill']) {
            return $checkout['payment_url'];
        }

        /** @var PaymentTransaction $payment */
        $payment = $checkout['payment'];

        try {
            $bill = $this->gateway->createPayment($payment);
        } catch (BillplzPaymentException $exception) {
            DB::transaction(function () use ($payment): void {
                $lockedPayment = PaymentTransaction::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $metadata = $lockedPayment->metadata ?? [];
                $metadata['billplz']['checkout_status'] = 'FAILED';

                $lockedPayment->update([
                    'status' => 'FAILED',
                    'metadata' => $metadata,
                ]);
                $lockedPayment->events()->create([
                    'event_type' => 'BILLPLZ_BILL_CREATION_FAILED',
                    'occurred_at' => now(),
                ]);
            });

            throw ValidationException::withMessages([
                'billplz' => 'The payment could not be started. Please try again.',
            ]);
        }

        return DB::transaction(function () use ($payment, $bill): string {
            $lockedPayment = PaymentTransaction::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $metadata = $lockedPayment->metadata ?? [];
            $metadata['billplz'] = array_merge($metadata['billplz'] ?? [], [
                'checkout_status' => 'READY',
                'checkout_url' => $bill['payment_url'],
            ]);

            $lockedPayment->update([
                'provider_reference' => $bill['provider_reference'],
                'metadata' => $metadata,
            ]);
            $lockedPayment->events()->create([
                'event_type' => 'BILLPLZ_BILL_CREATED',
                'payload' => ['bill_id' => $bill['provider_reference']],
                'occurred_at' => now(),
            ]);

            return $bill['payment_url'];
        }, 3);
    }

    private function toSen(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\d*)\.([0-9]{2})$/', $amount, $matches)) {
            throw ValidationException::withMessages([
                'billplz' => 'The balance amount in the price snapshot is invalid.',
            ]);
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }
}
