<?php

namespace Database\Seeders;

use App\Models\OrderPricingProfile;
use App\Models\PriceCatalogItem;
use Illuminate\Database\Seeder;

class KkkPackagePriceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            'PUTERI_MANIS' => [
                30 => 98,
                50 => 128,
                100 => 148,
                150 => 168,
                200 => 188,
                250 => 208,
                300 => 228,
                400 => 268,
                500 => 288,
                600 => 308,
                800 => 348,
                1000 => 368,
            ],
            'JELATA' => [
                30 => 50,
                50 => 69,
                100 => 89,
                200 => 109,
                300 => 119,
                400 => 129,
                500 => 139,
                700 => 209,
                800 => 229,
                1000 => 269,
            ],
        ];

        foreach ($tiers as $packageType => $packageTiers) {
            foreach ($packageTiers as $quantity => $price) {
                PriceCatalogItem::updateOrCreate(
                    ['code' => "{$packageType}_CARD_{$quantity}"],
                    [
                        'item_type' => 'PACKAGE_TIER',
                        'name' => "{$packageType} — {$quantity} cards",
                        'description' => "Price tier for {$quantity} cards in {$packageType}.",
                        'calculation_method' => 'FIXED_TIER',
                        'unit_price' => $price,
                        'unit_label' => 'tier',
                        'is_active' => true,
                        'metadata' => [
                            'package_type' => $packageType,
                            'card_quantity' => $quantity,
                            'package_counts' => [1, 2],
                        ],
                    ]
                );
            }
        }

        $specialCombos = [
            [
                'code' => 'PUTERI_MANIS_2P_30_50',
                'package_type' => 'PUTERI_MANIS',
                'price' => 226,
            ],
            [
                'code' => 'JELATA_2P_30_50',
                'package_type' => 'JELATA',
                'price' => 118,
            ],
        ];

        foreach ($specialCombos as $combo) {
            PriceCatalogItem::updateOrCreate(
                ['code' => $combo['code']],
                [
                    'item_type' => 'PACKAGE_COMBO',
                    'name' => "{$combo['package_type']} — two packages of 30 + 50 cards",
                    'description' => 'Two-package exception: price of 30 cards plus price of 50 cards.',
                    'calculation_method' => 'FIXED_COMBO',
                    'unit_price' => $combo['price'],
                    'unit_label' => 'combo',
                    'is_active' => true,
                    'metadata' => [
                        'package_type' => $combo['package_type'],
                        'package_count' => 2,
                        'side_quantities' => [30, 50],
                    ],
                ]
            );
        }

        foreach ([
            ['code' => 'V1_ONE_PACKAGE', 'name' => 'KKK OS V1 — one package', 'deposit' => 50],
            ['code' => 'V1_TWO_PACKAGES', 'name' => 'KKK OS V1 — two packages', 'deposit' => 100],
        ] as $profile) {
            OrderPricingProfile::updateOrCreate(
                ['code' => $profile['code']],
                [
                    'name' => $profile['name'],
                    'currency' => 'MYR',
                    'deposit_method' => 'FIXED',
                    'deposit_value' => $profile['deposit'],
                    'is_active' => true,
                ]
            );
        }
    }
}
