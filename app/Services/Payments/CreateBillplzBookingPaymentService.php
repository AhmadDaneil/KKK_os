<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Orders\ResolveBookingAmountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBillplzBookingPaymentService
{
    public function __construct(
        private BillplzPaymentGateway $gateway,
        private ResolveBookingAmountService $bookingAmount,
    ) {}

    /** @return array{payment: PaymentTransaction, payment_url: string} */
    public function create(Order $order, bool $throwGatewayFailure = false, string $paymentMethod = BillplzPaymentMethod::BILLPLZ): array
    {
        if (! config('services.billplz.enabled')) {
            if ($throwGatewayFailure) {
                throw new BillplzPaymentException('Billplz is not enabled.', 'disabled');
            }

            throw ValidationException::withMessages([
                'payment' => 'Online payments are not enabled.',
            ]);
        }

        BillplzPaymentMethod::assertEnabled($paymentMethod);

        $checkout = DB::transaction(function () use ($order, $paymentMethod): array {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->booking_payment_status === 'PAID') {
                throw ValidationException::withMessages([
                    'payment' => 'The booking fee for this order has already been paid.',
                ]);
            }

            if ($lockedOrder->status !== 'DETAILS_INCOMPLETE') {
                throw ValidationException::withMessages([
                    'payment' => 'The booking fee can only be paid before order details are confirmed.',
                ]);
            }

            $existing = $lockedOrder->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->whereIn('status', ['PENDING', 'PAID'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing?->status === 'PAID') {
                throw ValidationException::withMessages([
                    'payment' => 'The booking fee for this order has already been paid.',
                ]);
            }

            if ($existing !== null) {
                if (BillplzPaymentMethod::fromPayment($existing) !== $paymentMethod) {
                    throw ValidationException::withMessages([
                        'payment' => 'A payment is already in progress with a different method. Continue that payment or wait for it to expire.',
                    ]);
                }

                $url = $existing->metadata['billplz']['checkout_url'] ?? null;
                $configuredHost = parse_url((string) config('services.billplz.base_url', ''), PHP_URL_HOST);
                $checkoutHost = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
                $checkoutScheme = is_string($url) ? parse_url($url, PHP_URL_SCHEME) : null;

                if ($existing->provider === 'BILLPLZ'
                    && in_array($configuredHost, ['www.billplz.com', 'www.billplz-sandbox.com'], true)
                    && is_string($url)
                    && $url !== ''
                    && $checkoutScheme === 'https'
                    && $checkoutHost === $configuredHost) {
                    return ['payment' => $existing, 'payment_url' => $url, 'create_bill' => false];
                }

                if (is_string($url) && $url !== '') {
                    throw new BillplzPaymentException(
                        'The existing checkout belongs to a different Billplz environment.',
                        'checkout_environment_mismatch',
                    );
                }

                throw ValidationException::withMessages([
                    'payment' => 'The payment is being prepared. Please try again shortly.',
                ]);
            }

            $amount = $this->bookingAmount->resolve($lockedOrder);
            $payment = $lockedOrder->payments()->create([
                'payment_type' => 'BOOKING_DEPOSIT',
                'provider' => 'BILLPLZ',
                'public_reference' => Str::random(48),
                'amount' => $amount['amount'],
                'currency' => $amount['currency'],
                'status' => 'PENDING',
                'metadata' => ['billplz' => [
                    'checkout_status' => 'CREATING',
                    'payment_method' => $paymentMethod,
                ]],
            ]);
            $payment->events()->create([
                'event_type' => 'BOOKING_PAYMENT_CREATED',
                'payload' => [
                    'amount' => $amount['amount'],
                    'currency' => $amount['currency'],
                    'payment_method' => $paymentMethod,
                ],
                'occurred_at' => now(),
            ]);
            $lockedOrder->update(['booking_payment_status' => 'PENDING']);

            return ['payment' => $payment, 'payment_url' => '', 'create_bill' => true];
        }, 3);

        if (! $checkout['create_bill']) {
            return ['payment' => $checkout['payment'], 'payment_url' => $checkout['payment_url']];
        }

        $payment = $checkout['payment'];
        try {
            $bill = $this->gateway->createPayment($payment);
        } catch (BillplzPaymentException $exception) {
            DB::transaction(function () use ($payment, $exception): void {
                $locked = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $metadata = $locked->metadata ?? [];
                $uncertain = $exception->reason === 'connection_failed';
                $metadata['billplz']['checkout_status'] = $uncertain ? 'UNKNOWN' : 'FAILED';
                $locked->update(['status' => $uncertain ? 'PENDING' : 'FAILED', 'metadata' => $metadata]);
                $locked->events()->create([
                    'event_type' => $uncertain ? 'BILLPLZ_BILL_CREATION_UNCERTAIN' : 'BILLPLZ_BILL_CREATION_FAILED',
                    'payload' => [
                        'reason' => $exception->reason,
                        'provider_status' => $exception->providerStatus,
                    ],
                    'occurred_at' => now(),
                ]);
            });

            Log::warning('Billplz booking bill creation failed.', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'reason' => $exception->reason,
                'provider_status' => $exception->providerStatus,
            ]);

            if ($throwGatewayFailure) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'payment' => 'The payment could not be started. Please try again.',
            ]);
        }

        $payment = DB::transaction(function () use ($payment, $bill): PaymentTransaction {
            $locked = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $metadata = $locked->metadata ?? [];
            $metadata['billplz'] = array_merge($metadata['billplz'] ?? [], [
                'checkout_status' => 'READY',
                'checkout_url' => $bill['payment_url'],
            ]);
            $locked->update([
                'provider_reference' => $bill['provider_reference'],
                'metadata' => $metadata,
            ]);
            $locked->events()->create([
                'event_type' => 'BILLPLZ_BILL_CREATED',
                'payload' => ['bill_id' => $bill['provider_reference']],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        }, 3);

        return ['payment' => $payment, 'payment_url' => $bill['payment_url']];
    }
}
