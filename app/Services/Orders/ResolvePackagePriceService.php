<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\PriceCatalogItem;
use Illuminate\Validation\ValidationException;

class ResolvePackagePriceService
{
    /**
     * Resolve the base package price from the active catalog.
     *
     * @return array{
     *     catalog_item: PriceCatalogItem,
     *     package_type: string,
     *     package_count: int,
     *     card_quantity: int,
     *     side_quantities: array<string, int>,
     *     amount: string
     * }
     */
    public function resolve(Order $order): array
    {
        $order->loadMissing('packageSides');

        $packageType = (string) $order->package_type;
        $packageCount = (int) $order->package_count;

        if (! in_array($packageType, ['PUTERI_MANIS', 'JELATA'], true)) {
            throw ValidationException::withMessages([
                'package_type' => 'Please select a valid package type.',
            ]);
        }

        if (! in_array($packageCount, [1, 2], true)) {
            throw ValidationException::withMessages([
                'package_count' => 'Invalid package count.',
            ]);
        }

        $allowedSideQuantities = PriceCatalogItem::query()
            ->where('item_type', 'PACKAGE_TIER')
            ->where('is_active', true)
            ->get()
            ->filter(
                fn ($item) => data_get($item->metadata, 'package_type') === $packageType
            )
            ->map(fn ($item) => (int) data_get($item->metadata, 'card_quantity'))
            ->filter(fn ($quantity) => $quantity > 0)
            ->unique()
            ->values()
            ->all();

        $sideQuantities = [];

        foreach ($order->packageSides as $side) {
            if (! in_array($side->side, ['LELAKI', 'PEREMPUAN'], true)) {
                throw ValidationException::withMessages([
                    'card_quantity' => 'Invalid package side.',
                ]);
            }

            if ($side->card_quantity === null || (int) $side->card_quantity < 1) {
                throw ValidationException::withMessages([
                    'card_quantity' => 'The card quantity for each side is required.',
                ]);
            }

            if (! in_array((int) $side->card_quantity, $allowedSideQuantities, true)) {
                throw ValidationException::withMessages([
                    "sides.{$side->side}.card_quantity" =>
                        'The card quantity for this side is not available in any package tier.',
                ]);
            }

            $sideQuantities[$side->side] = (int) $side->card_quantity;
        }

        $expectedSides = $packageCount === 1
            ? count($sideQuantities) === 1
            : count($sideQuantities) === 2
                && isset($sideQuantities['LELAKI'], $sideQuantities['PEREMPUAN']);

        if (! $expectedSides) {
            throw ValidationException::withMessages([
                'card_quantity' => 'The card quantity for each side is incomplete.',
            ]);
        }

        $totalQuantity = array_sum($sideQuantities);

        if ((int) $order->card_quantity !== $totalQuantity) {
            throw ValidationException::withMessages([
                'card_quantity' => 'The total card quantity does not match the quantities for each side.',
            ]);
        }

        $sortedQuantities = array_values($sideQuantities);
        sort($sortedQuantities);

        $isSpecialCombo = $packageCount === 2 && $sortedQuantities === [30, 50];

        $itemCode = $isSpecialCombo
            ? "{$packageType}_2P_30_50"
            : "{$packageType}_CARD_{$totalQuantity}";

        $item = PriceCatalogItem::query()
            ->where('code', $itemCode)
            ->where('is_active', true)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'card_quantity' => 'This quantity combination does not match an available price tier.',
            ]);
        }

        $metadata = $item->metadata ?? [];

        if (($metadata['package_type'] ?? null) !== $packageType) {
            throw ValidationException::withMessages([
                'package_type' => 'The catalog price item does not match the package type.',
            ]);
        }

        if ($isSpecialCombo) {
            if (
                (int) ($metadata['package_count'] ?? 0) !== 2
                || ($metadata['side_quantities'] ?? null) !== [30, 50]
            ) {
                throw ValidationException::withMessages([
                    'card_quantity' => 'The special 30 + 50 combination is not configured correctly.',
                ]);
            }
        } else {
            $allowedPackageCounts = array_map(
                'intval',
                $metadata['package_counts'] ?? []
            );

            if (
                (int) ($metadata['card_quantity'] ?? 0) !== $totalQuantity
                || ! in_array($packageCount, $allowedPackageCounts, true)
            ) {
                throw ValidationException::withMessages([
                    'card_quantity' => 'The catalog price tier does not match the order quantity.',
                ]);
            }
        }

        return [
            'catalog_item' => $item,
            'package_type' => $packageType,
            'package_count' => $packageCount,
            'card_quantity' => $totalQuantity,
            'side_quantities' => $sideQuantities,
            'amount' => (string) $item->unit_price,
        ];
    }
}
