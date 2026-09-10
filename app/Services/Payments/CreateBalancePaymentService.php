<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateBalancePaymentService
{
    public function create(Order $order, string $amount): PaymentTransaction
    {
        return DB::transaction(function () use ($order, $amount) {
            $order->refresh();

            // Idempotency first:
            // if an active balance payment already exists,
            // return it instead of creating another one.
            $existing = PaymentTransaction::where('order_id', $order->id)
                ->where('payment_type', 'BALANCE')
                ->whereIn('status', ['PENDING', 'PAID'])
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            // Only a NEW balance payment requires DESIGN_APPROVED.
            if ($order->status !== 'DESIGN_APPROVED') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be DESIGN_APPROVED before balance payment can be created."
                );
            }

            $payment = PaymentTransaction::create([
                'order_id' => $order->id,
                'payment_type' => 'BALANCE',
                'provider' => 'TEST',
                'amount' => $amount,
                'currency' => 'MYR',
                'status' => 'PENDING',
            ]);

            $payment->events()->create([
                'event_type' => 'BALANCE_PAYMENT_CREATED',
                'occurred_at' => now(),
            ]);

            $order->update([
                'status' => 'BALANCE_PENDING',
            ]);

            return $payment->fresh();
        });
    }
}