<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Invoices\GenerateInvoiceNumberService;
use App\Services\Orders\GenerateCustomerReferenceService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SynchronizeOrderFinancialsService
{
    public function __construct(
        private GenerateCustomerReferenceService $references,
        private GenerateInvoiceNumberService $invoiceNumbers,
    ) {}

    public function synchronize(Order $order): ?Invoice
    {
        return DB::transaction(function () use ($order): ?Invoice {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $snapshot = $lockedOrder->pricingSnapshot()->lockForUpdate()->first();

            if ($snapshot === null) {
                return null;
            }

            $currency = $snapshot->currency;

            $paidCents = $lockedOrder->payments()
                ->where('status', 'PAID')
                ->where('currency', $currency)
                ->get(['amount'])
                ->sum(fn ($payment): int => $this->toCents((string) $payment->amount));

            if ($paidCents <= 0) {
                return null;
            }

            $totalCents = $this->toCents((string) $snapshot->total_amount);

            if ($totalCents <= 0 || $paidCents > $totalCents) {
                throw new RuntimeException('The total payments exceed the order price snapshot.');
            }

            $balanceCents = $totalCents - $paidCents;
            $snapshot?->update([
                'paid_amount' => $this->fromCents($paidCents),
                'outstanding_amount' => $this->fromCents($balanceCents),
            ]);

            if ($lockedOrder->booking_payment_status !== 'PAID') {
                return null;
            }

            $customerReference = $this->references->generate($lockedOrder);

            $paymentStatus = match (true) {
                $balanceCents === 0 => 'PAID',
                $paidCents > 0 => 'PARTIALLY_PAID',
                default => 'UNPAID',
            };
            $values = [
                'currency' => $currency,
                'subtotal' => $snapshot?->subtotal ?? $this->fromCents($totalCents),
                'postage_amount' => $snapshot?->postage_amount ?? '0.00',
                'discount_amount' => $snapshot?->discount_amount ?? '0.00',
                'total_amount' => $snapshot?->total_amount ?? $this->fromCents($totalCents),
                'amount_paid' => $this->fromCents($paidCents),
                'balance_due' => $this->fromCents($balanceCents),
                'payment_status' => $paymentStatus,
            ];

            $invoice = Invoice::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();

            if ($invoice === null) {
                return Invoice::query()->create([
                    'order_id' => $lockedOrder->id,
                    'invoice_number' => $this->invoiceNumbers->generate($lockedOrder, $customerReference),
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
            throw new RuntimeException('The financial amount is not a valid value with two decimal places.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    private function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
