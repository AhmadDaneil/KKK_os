<?php

namespace App\Services\Orders;

use App\Models\PriceCatalogItem;

class BuildTestOrderQuantityOptionsService
{
    /**
     * @return array<string, array{
     *     single: list<array{quantity: int, amount: string}>,
     *     pairs: list<array{value: string, groom: int, bride: int, total: int, amount: string}>
     * }>
     */
    public function build(): array
    {
        $items = PriceCatalogItem::query()
            ->whereIn('item_type', ['PACKAGE_TIER', 'PACKAGE_COMBO'])
            ->where('is_active', true)
            ->get();

        $tiers = [];
        $specialCombos = [];

        foreach ($items as $item) {
            $packageType = (string) data_get($item->metadata, 'package_type');

            if (! in_array($packageType, ['PUTERI_MANIS', 'JELATA'], true)) {
                continue;
            }

            if ($item->item_type === 'PACKAGE_TIER') {
                $quantity = (int) data_get($item->metadata, 'card_quantity');

                if ($quantity > 0) {
                    $tiers[$packageType][$quantity] = [
                        'amount' => (string) $item->unit_price,
                        'package_counts' => array_map('intval', data_get($item->metadata, 'package_counts', [])),
                    ];
                }
            } elseif (
                $item->item_type === 'PACKAGE_COMBO'
                && (int) data_get($item->metadata, 'package_count') === 2
            ) {
                $quantities = array_map('intval', data_get($item->metadata, 'side_quantities', []));
                sort($quantities);

                if ($quantities === [30, 50]) {
                    $specialCombos[$packageType] = (string) $item->unit_price;
                }
            }
        }

        $options = [];

        foreach (['PUTERI_MANIS', 'JELATA'] as $packageType) {
            $packageTiers = $tiers[$packageType] ?? [];
            $single = [];
            $pairs = [];

            foreach ($packageTiers as $quantity => $tier) {
                if (in_array(1, $tier['package_counts'], true)) {
                    $single[] = [
                        'quantity' => (int) $quantity,
                        'amount' => $tier['amount'],
                    ];
                }
            }

            $sideQuantities = array_map('intval', array_keys($packageTiers));

            foreach ($sideQuantities as $groomQuantity) {
                foreach ($sideQuantities as $brideQuantity) {
                    $totalQuantity = $groomQuantity + $brideQuantity;
                    $tier = $packageTiers[$totalQuantity] ?? null;

                    if ($tier === null || ! in_array(2, $tier['package_counts'], true)) {
                        continue;
                    }

                    $value = $groomQuantity.':'.$brideQuantity;
                    $pairs[$value] = [
                        'value' => $value,
                        'groom' => $groomQuantity,
                        'bride' => $brideQuantity,
                        'total' => $totalQuantity,
                        'amount' => $tier['amount'],
                    ];
                }
            }

            if (
                isset($specialCombos[$packageType])
                && in_array(30, $sideQuantities, true)
                && in_array(50, $sideQuantities, true)
            ) {
                foreach ([[30, 50], [50, 30]] as [$groomQuantity, $brideQuantity]) {
                    $value = $groomQuantity.':'.$brideQuantity;
                    $pairs[$value] = [
                        'value' => $value,
                        'groom' => $groomQuantity,
                        'bride' => $brideQuantity,
                        'total' => 80,
                        'amount' => $specialCombos[$packageType],
                    ];
                }
            }

            usort(
                $single,
                fn (array $left, array $right): int => $left['quantity'] <=> $right['quantity']
            );
            uasort(
                $pairs,
                fn (array $left, array $right): int => [$left['groom'], $left['bride']] <=> [$right['groom'], $right['bride']]
            );

            $options[$packageType] = [
                'single' => $single,
                'pairs' => array_values($pairs),
            ];
        }

        return $options;
    }
}
