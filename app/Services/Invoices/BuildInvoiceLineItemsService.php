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

        if ($packageItems->isEmpty()) {
            $rows[] = $this->row(
                $this->packageDescription($order),
                1,
                (float) $invoice->subtotal,
                (float) $invoice->subtotal,
            );
        } else {
            foreach ($packageItems as $item) {
                $rows[] = $this->row(
                    $this->packageDescription($order),
                    1,
                    (float) $item->unit_price,
                    (float) $item->line_total,
                    $item->side,
                );
            }
        }

        if ($order->package_type === 'PUTERI_MANIS') {
            $rows[] = $this->row('FREE Motion Video', 1, 0, 0);
            $rows[] = $this->row('FREE Wooden Hanger 1 pc', 1, 0, 0);
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

    private function packageDescription(Order $order): string
    {
        if ((int) $order->card_quantity < 1) {
            return "Wedding card order ({$order->package_count} packages)";
        }

        $quantity = number_format((int) $order->card_quantity);

        if ($order->package_type === 'PUTERI_MANIS') {
            $stickerQuantity = max(0, (int) $order->card_quantity - 50);

            return implode('; ', [
                "Kad Kahwin 4x6 {$quantity} pcs",
                "White Envelope 4x6 {$quantity} pcs",
                'Banting 2x4 1 pc',
                'Banner 3x6 1 pc',
                'Arrow A4 4 pcs',
                'Sticker 3cm '.number_format($stickerQuantity).' pcs',
                'E-Card (PDF File)',
                'Alive Card Premium',
            ]);
        }

        if ($order->package_type === 'JELATA') {
            return implode('; ', [
                "Kad Kahwin 4x6 {$quantity} pcs",
                'Live Card',
                "Sampul {$quantity} pcs",
            ]);
        }

        return "Wedding card order ({$order->package_count} packages)";
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
