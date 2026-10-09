<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SynchronizeOrderFinancialsService
{
    public function synchronize(Order $order): ?Invoice
    {
        return DB::transaction(function () use ($order): ?Invoice {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $snapshot = $lockedOrder->pricingSnapshot()->lockForUpdate()->first();

            if ($snapshot === null) {
                return null;
            }

            $totalCents = $this->toCents((string) $snapshot->total_amount);
            if ($totalCents <= 0) {
                return null;
            }

            $paidCents = $lockedOrder->payments()
                ->where('status', 'PAID')
                ->where('currency', $snapshot->currency)
                ->get(['amount'])
                ->sum(fn ($payment): int => $this->toCents((string) $payment->amount));

            if ($paidCents <= 0) {
                return null;
            }

            if ($paidCents > $totalCents) {
                throw new RuntimeException('Jumlah bayaran order melebihi jumlah keseluruhan snapshot harga.');
            }

            $balanceCents = $totalCents - $paidCents;
            $snapshot->update([
                'paid_amount' => $this->fromCents($paidCents),
                'outstanding_amount' => $this->fromCents($balanceCents),
            ]);

            if ($lockedOrder->booking_payment_status !== 'PAID') {
                return null;
            }

            $paymentStatus = match (true) {
                $balanceCents === 0 => 'PAID',
                $paidCents > 0 => 'PARTIALLY_PAID',
                default => 'UNPAID',
            };
            $values = [
                'currency' => $snapshot->currency,
                'subtotal' => $snapshot->subtotal,
                'postage_amount' => $snapshot->postage_amount,
                'discount_amount' => $snapshot->discount_amount,
                'total_amount' => $snapshot->total_amount,
                'amount_paid' => $this->fromCents($paidCents),
                'balance_due' => $this->fromCents($balanceCents),
                'payment_status' => $paymentStatus,
            ];

            $invoice = Invoice::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();

            if ($invoice === null) {
                return Invoice::query()->create([
                    'order_id' => $lockedOrder->id,
                    'invoice_number' => 'INV-'.$lockedOrder->order_id,
                    ...$values,
                    'issued_at' => now(),
                ]);
            }

            $invoice->update($values);

            return $invoice->fresh();
        }, 3);
    }

    private function toCents(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\d*)\.([0-9]{2})$/', $amount, $matches)) {
            throw new RuntimeException('Amaun kewangan bukan nilai perpuluhan dua tempat yang sah.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    private function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
