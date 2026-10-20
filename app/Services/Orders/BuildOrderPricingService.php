<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPricingProfile;
use App\Models\OrderPricingSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuildOrderPricingService
{
    public function __construct(
        private ResolvePackagePriceService $packagePriceResolver,
        private CalculateOrderPostageService $postageCalculator,
    ) {}

    /**
     * @return array{
     *     package: array,
     *     profile: OrderPricingProfile,
     *     currency: string,
     *     package_amount: string,
     *     postage_amount: string,
     *     delivery: array{amount: string, region: string|null, region_label: string, description: string},
     *     deposit_amount: string,
     *     total_amount: string
     * }
     */
    public function quote(Order $order): array
    {
        $package = $this->packagePriceResolver->resolve($order);
        $profileCode = match ((int) $order->package_count) {
            1 => 'V1_ONE_PACKAGE',
            2 => 'V1_TWO_PACKAGES',
            default => null,
        };

        $profile = $profileCode === null
            ? null
            : OrderPricingProfile::query()
                ->where('code', $profileCode)
                ->where('is_active', true)
                ->first();

        if ($profile === null || $profile->deposit_method !== 'FIXED') {
            throw ValidationException::withMessages([
                'pricing' => 'An active pricing and deposit profile is not configured for this order.',
            ]);
        }

        $packageCents = $this->toCents((string) $package['amount']);
        $delivery = $this->postageCalculator->quote($order);
        $postageCents = $this->toCents($delivery['amount']);
        $depositCents = $this->toCents((string) $profile->deposit_value);

        if ($depositCents > ($packageCents + $postageCents)) {
            throw ValidationException::withMessages([
                'pricing' => 'The deposit cannot exceed the total order price.',
            ]);
        }

        return [
            'package' => $package,
            'profile' => $profile,
            'currency' => $profile->currency,
            'package_amount' => $this->fromCents($packageCents),
            'postage_amount' => $this->fromCents($postageCents),
            'delivery' => $delivery,
            'deposit_amount' => $this->fromCents($depositCents),
            'total_amount' => $this->fromCents($packageCents + $postageCents),
        ];
    }

    public function createSnapshot(Order $order): OrderPricingSnapshot
    {
        return DB::transaction(function () use ($order): OrderPricingSnapshot {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $lockedOrder->pricingSnapshot()->first();

            if ($existing !== null) {
                return $existing;
            }

            $quote = $this->quote($lockedOrder->fresh('packageSides'));
            $package = $quote['package'];
            $packageAmount = $quote['package_amount'];
            $postageAmount = $quote['postage_amount'];
            $totalAmount = $quote['total_amount'];

            $snapshot = $lockedOrder->pricingSnapshot()->create([
                'pricing_profile_id' => $quote['profile']->id,
                'currency' => $quote['currency'],
                'subtotal' => $packageAmount,
                'postage_amount' => $postageAmount,
                'discount_amount' => '0.00',
                'total_amount' => $totalAmount,
                'booking_amount' => $quote['deposit_amount'],
                'paid_amount' => '0.00',
                'outstanding_amount' => $totalAmount,
                'calculation_snapshot' => [
                    'package_type' => $package['package_type'],
                    'package_count' => $package['package_count'],
                    'card_quantity' => $package['card_quantity'],
                    'side_quantities' => $package['side_quantities'],
                    'catalog_item_code' => $package['catalog_item']->code,
                    'catalog_item_name' => $package['catalog_item']->name,
                    'package_amount' => $quote['package_amount'],
                    'postage_amount' => $postageAmount,
                    'postage_region' => $quote['delivery']['region'],
                    'deposit_profile_code' => $quote['profile']->code,
                    'deposit_amount' => $quote['deposit_amount'],
                ],
            ]);

            OrderItem::query()->create([
                'order_id' => $lockedOrder->id,
                'price_catalog_item_id' => $package['catalog_item']->id,
                'item_type' => 'PACKAGE',
                'item_code' => $package['catalog_item']->code,
                'description' => $package['catalog_item']->name,
                'quantity' => 1,
                'unit_price' => $packageAmount,
                'line_total' => $packageAmount,
                'metadata' => [
                    'package_type' => $package['package_type'],
                    'package_count' => $package['package_count'],
                    'card_quantity' => $package['card_quantity'],
                    'side_quantities' => $package['side_quantities'],
                ],
            ]);

            if ($this->toCents($postageAmount) > 0) {
                OrderItem::query()->create([
                    'order_id' => $lockedOrder->id,
                    'item_type' => 'DELIVERY',
                    'item_code' => 'POSTAGE_'.str_replace('/', '_', (string) $quote['delivery']['region']),
                    'description' => $quote['delivery']['description'],
                    'quantity' => 1,
                    'unit_price' => $postageAmount,
                    'line_total' => $postageAmount,
                    'metadata' => [
                        'region' => $quote['delivery']['region'],
                        'package_type' => $package['package_type'],
                        'card_quantity' => $package['card_quantity'],
                    ],
                ]);
            }

            return $snapshot;
        }, 3);
    }

    private function toCents(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\d*)\.([0-9]{2})$/', $amount, $matches)) {
            throw ValidationException::withMessages([
                'pricing' => 'The price or deposit amount is not a valid monetary value.',
            ]);
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    private function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
