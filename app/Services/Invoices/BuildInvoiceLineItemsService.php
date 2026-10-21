<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Orders\CalculateOrderPostageService;

class BuildInvoiceLineItemsService
{
    public function __construct(private CalculateOrderPostageService $postage) {}

    /** @return list<array{description: string, quantity: int, unit_price: float, line_total: float, side: string|null}> */
    public function build(Order $order, Invoice $invoice): array
    {
        $rows = [];
        $packageItems = $order->items->where('item_type', 'PACKAGE');
        $packageTotal = $packageItems->isEmpty()
            ? (float) $invoice->subtotal
            : (float) $packageItems->sum('line_total');
        $packageUnits = max(1, (int) $order->package_count);
        $packageDescription = $this->packageName($order);

        $rows[] = $this->row(
            $packageDescription,
            $packageUnits,
            $packageTotal / $packageUnits,
            $packageTotal,
        );

        $sideQuantities = $this->sideQuantities($order);
        $totalQuantity = array_sum(array_column($sideQuantities, 'quantity'));

        if ($order->package_type === 'PUTERI_MANIS' && $totalQuantity > 0) {
            foreach ($sideQuantities as $sideQuantity) {
                $quantity = $sideQuantity['quantity'];
                $side = $sideQuantity['side'];
                $rows[] = $this->row("Kad Kahwin 4x6 {$quantity} pcs", $quantity, 0, 0, $side);
                $rows[] = $this->row("White Envelope 4x6 {$quantity} pcs", $quantity, 0, 0, $side);
            }

            $rows[] = $this->row('Banting 2x4 1 pc', 1, 0, 0);
            $rows[] = $this->row('Banner 3x6 1 pc', 1, 0, 0);
            $rows[] = $this->row('Arrow A4 4 pcs', 4, 0, 0);
            $rows[] = $this->row('Sticker 3cm '.number_format(max(0, $totalQuantity - 50)).' pcs', max(0, $totalQuantity - 50), 0, 0);
            $rows[] = $this->row('E-Card (PDF File)', 1, 0, 0);
            $rows[] = $this->row('FREE Alive Card Premium', 1, 0, 0);
            $rows[] = $this->row('FREE Motion Video', 1, 0, 0);
            $rows[] = $this->row('FREE Wooden Hanger 1 pc', 1, 0, 0);
        } elseif ($order->package_type === 'JELATA' && $totalQuantity > 0) {
            foreach ($sideQuantities as $sideQuantity) {
                $quantity = $sideQuantity['quantity'];
                $side = $sideQuantity['side'];
                $rows[] = $this->row("Kad Kahwin 4x6 {$quantity} pcs", $quantity, 0, 0, $side);
                $rows[] = $this->row("Sampul {$quantity} pcs", $quantity, 0, 0, $side);
            }

            $rows[] = $this->row('Live Card', 1, 0, 0);
        }

        foreach ($order->items->whereNotIn('item_type', ['PACKAGE', 'DELIVERY', 'DISCOUNT']) as $item) {
            $rows[] = $this->row(
                (string) $item->description,
                (int) $item->quantity,
                (float) $item->unit_price,
                (float) $item->line_total,
                $item->side,
            );
        }

        if ((float) $invoice->postage_amount > 0) {
            $deliveryItem = $order->items->firstWhere('item_type', 'DELIVERY');
            $postageQuote = $this->postage->quote($order);
            $rows[] = $this->row(
                (string) ($deliveryItem?->description ?: $postageQuote['description']),
                1,
                (float) $invoice->postage_amount,
                (float) $invoice->postage_amount,
            );
        }

        if ((float) $invoice->discount_amount > 0) {
            $discountItem = $order->items->firstWhere('item_type', 'DISCOUNT');
            $discountAmount = (float) $invoice->discount_amount;
            $rows[] = $this->row(
                (string) ($discountItem?->description ?: 'Discount'),
                1,
                -$discountAmount,
                -$discountAmount,
            );
        }

        return $rows;
    }

    public function deliveryLabel(Order $order): string
    {
        return $this->postage->quote($order)['region_label'];
    }

    private function packageName(Order $order): string
    {
        return match ($order->package_type) {
            'PUTERI_MANIS' => 'Pakej Puteri Manis',
            'JELATA' => 'Pakej Jelata',
            default => "Wedding card order ({$order->package_count} packages)",
        };
    }

    /** @return list<array{side: string|null, quantity: int}> */
    private function sideQuantities(Order $order): array
    {
        $sides = $order->packageSides
            ->map(fn ($side): array => [
                'side' => $side->side,
                'quantity' => (int) $side->card_quantity,
            ])
            ->filter(fn (array $side): bool => $side['quantity'] > 0)
            ->values()
            ->all();

        if ($sides !== []) {
            return $sides;
        }

        $quantity = (int) $order->card_quantity;

        if ($quantity < 1) {
            return [];
        }

        $onlySide = $order->packageSides->count() === 1
            ? $order->packageSides->first()?->side
            : null;

        return [['side' => $onlySide, 'quantity' => $quantity]];
    }

    /** @return array{description: string, quantity: int, unit_price: float, line_total: float, side: string|null} */
    private function row(string $description, int $quantity, float $unitPrice, float $lineTotal, ?string $side = null): array
    {
        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'side' => $side,
        ];
    }
}
