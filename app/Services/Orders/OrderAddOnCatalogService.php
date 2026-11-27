<?php

namespace App\Services\Orders;

class OrderAddOnCatalogService
{
    /**
     * @return array<string, array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     base_unit_price_cents: int,
     *     postage_cents: int,
     *     unit_price_cents: int,
     *     min_quantity: int,
     *     option_label: string
     * }>
     */
    public function options(): array
    {
        $definitions = [
            'BANTING_2X4' => ['Banting 2x4', 1900],
            'BANNER_3X6' => ['Banner 3x6', 3900],
            'ARROW_SIGN_A4_2PCS' => ['Arrow Sign A4 2 pcs', 300],
            'RUBBER_STAMP_SELF_INK' => ['Rubber Stamp Self Ink', 4900],
            'RUBBER_STAMP_PLASTIC' => ['Rubber Stamp Plastic', 3500],
            'WOODEN_HANGER_ENGRAVED' => ['Wooden Hanger Engraved', 1000],
            'MOCKUP_CHEQUE_GOLD_ROSEGOLD_SILVER' => ['Mockup Cheque - Gold / Rosegold / Silver', 4900],
            'MOCKUP_CHEQUE_CRYSTAL_TRANSPARENT' => ['Mockup Cheque - Crystal Transparent', 2900],
            'WELCOME_BOARD_CRYSTAL_TRANSPARENT' => ['Welcome Board - Crystal Transparent', 5900, 1500],
            'WELCOME_BOARD_FROSTED_BLACK_WHITE' => ['Welcome Board - Frosted / Black / White', 7900, 1500],
            'WELCOME_BOARD_MIRROR_GOLD_ROSEGOLD_SILVER' => ['Welcome Board - Mirror Gold / Rosegold / Silver', 9900, 1500],
            'WELCOME_BOARD_WOODEN_STAND' => ['Welcome Board Wooden Stand', 3900],
            'WELCOME_BOARD_STEEL_STAND' => ['Welcome Board Steel Stand', 5900],
            'BANTING_STEEL_STAND' => ['Banting Steel Stand', 3900],
            'WEDDING_BOX_READY_MADE' => ['Wedding Box Ready Made', 39],
            'GUEST_BOOK_A4' => ['Guest Book A4', 1900],
            'BUTTON_BADGE_READY_MADE' => ['Button Badge Ready Made (MOQ 10 pcs)', 150, 0, 10],
            'HOME_SIGNAGE_2_LAYER' => ['Home Signage 2 Layer', 7900],
            'HOME_SIGNAGE_1_LAYER' => ['Home Signage 1 Layer', 4900],
        ];

        $options = [];

        foreach ($definitions as $code => $definition) {
            [$label, $basePriceCents] = $definition;
            $postageCents = (int) ($definition[2] ?? 0);
            $minimumQuantity = (int) ($definition[3] ?? 1);
            $unitPriceCents = $basePriceCents + $postageCents;
            $optionLabel = $label.' — RM '.$this->formatCents($basePriceCents);

            if ($postageCents > 0) {
                $optionLabel .= ' + RM '.$this->formatCents($postageCents)
                    .' postage = RM '.$this->formatCents($unitPriceCents);
            } elseif ($minimumQuantity > 1) {
                $optionLabel .= ' each (minimum '.$minimumQuantity.' pcs)';
            }

            $options[$code] = [
                'code' => $code,
                'label' => $label,
                'description' => $postageCents > 0
                    ? $label.' (includes RM '.$this->formatCents($postageCents).' postage)'
                    : $label,
                'base_unit_price_cents' => $basePriceCents,
                'postage_cents' => $postageCents,
                'unit_price_cents' => $unitPriceCents,
                'min_quantity' => $minimumQuantity,
                'option_label' => $optionLabel,
            ];
        }

        return $options;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->options());
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     base_unit_price_cents: int,
     *     postage_cents: int,
     *     unit_price_cents: int,
     *     min_quantity: int,
     *     option_label: string
     * }|null
     */
    public function find(string $code): ?array
    {
        return $this->options()[$code] ?? null;
    }

    public function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
