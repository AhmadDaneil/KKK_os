<?php

namespace App\Services\Packing;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Invoices\BuildInvoiceLineItemsService;

class BuildPackingChecklistService
{
    public function __construct(private BuildInvoiceLineItemsService $invoiceItems) {}

    /** @return list<array{key: string, description: string, quantity: int, side: ?string}> */
    public function build(Order $order): array
    {
        $order->loadMissing(['items', 'packageSides']);
        $rows = $this->invoiceItems->build($order, new Invoice([
            'subtotal' => 0, 'postage_amount' => 0, 'discount_amount' => 0,
        ]));
        array_shift($rows);

        return collect($rows)
            ->reject(fn (array $row): bool => in_array($row['description'], [
                'E-Card (PDF File)', 'FREE Alive Card Premium', 'FREE Motion Video', 'Live Card',
            ], true))
            ->filter(fn (array $row): bool => $row['quantity'] > 0)
            ->values()
            ->map(fn (array $row, int $index): array => [
                'key' => hash('sha256', $index.'|'.$row['side'].'|'.$row['description'].'|'.$row['quantity']),
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'side' => $row['side'],
            ])
            ->all();
    }
}
