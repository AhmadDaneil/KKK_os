<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class CalculateOrderPostageService
{
    /** @return array{amount: string, region: string|null, region_label: string, description: string} */
    public function quote(Order $order): array
    {
        $order->loadMissing('fulfilment');

        if ($order->fulfilment?->method !== 'COURIER') {
            return [
                'amount' => '0.00',
                'region' => null,
                'region_label' => $order->fulfilment?->method === 'PICKUP' ? 'Self pickup' : 'Not set',
                'description' => '',
            ];
        }

        $quantity = (int) $order->card_quantity;
        $packageType = (string) $order->package_type;

        if ($quantity < 1 || ! in_array($packageType, ['PUTERI_MANIS', 'JELATA'], true)) {
            throw ValidationException::withMessages([
                'pricing' => 'The package type and card quantity are required to calculate postage.',
            ]);
        }

        $isEastMalaysia = preg_match('/\b(?:sabah|sarawak|labuan)\b/i', (string) $order->fulfilment?->shipping_address) === 1;
        $region = $isEastMalaysia ? 'SABAH/SARAWAK' : 'SEMENANJUNG';

        if ($packageType === 'PUTERI_MANIS') {
            $amount = $isEastMalaysia
                ? $this->tieredPrice($quantity, [50 => 50, 300 => 70, 600 => 85, 700 => 100, 900 => 115, 1000 => 130, 1500 => 150])
                : $this->tieredPrice($quantity, [200 => 25, 500 => 30, 800 => 35, 1000 => 40]);
            $packageName = 'Puteri Manis';
        } else {
            $amount = $this->tieredPrice($quantity, [200 => 20, 500 => 25, 800 => 30, 1000 => 35]);
            if ($isEastMalaysia) {
                $amount += 25;
            }
            $packageName = 'Jelata';
        }

        $regionLabel = $isEastMalaysia ? 'Postage Sabah/Sarawak' : 'Postage Semenanjung';

        return [
            'amount' => number_format($amount, 2, '.', ''),
            'region' => $region,
            'region_label' => $regionLabel,
            'description' => "{$regionLabel} - {$packageName} {$quantity} pcs",
        ];
    }

    /** @param array<int, int> $tiers Maximum quantity => postage price in RM. */
    private function tieredPrice(int $quantity, array $tiers): int
    {
        foreach ($tiers as $maximumQuantity => $price) {
            if ($quantity <= $maximumQuantity) {
                return $price;
            }
        }

        throw ValidationException::withMessages([
            'pricing' => 'The selected quantity does not have a postage rate in the current price list.',
        ]);
    }
}
