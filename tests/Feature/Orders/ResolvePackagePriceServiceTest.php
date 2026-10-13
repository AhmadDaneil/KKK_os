<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\PriceCatalogItem;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\ResolvePackagePriceService;
use App\Services\Orders\SaveOrderDraftService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResolvePackagePriceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KkkPackagePriceCatalogSeeder::class);
    }

    public function test_one_package_uses_its_exact_catalog_tier(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', ['LELAKI' => 100]);

        $result = app(ResolvePackagePriceService::class)->resolve($order);

        $this->assertSame('PUTERI_MANIS_CARD_100', $result['catalog_item']->code);
        $this->assertSame(100, $result['card_quantity']);
        $this->assertSame('148.00', $result['amount']);
    }

    public function test_jelata_one_package_30_piece_tier_matches_fixed_deposit(): void
    {
        $order = $this->draftOrder('JELATA', ['LELAKI' => 30]);

        $quote = app(BuildOrderPricingService::class)->quote($order);

        $this->assertSame('JELATA_CARD_30', $quote['package']['catalog_item']->code);
        $this->assertSame('50.00', $quote['package_amount']);
        $this->assertSame('50.00', $quote['deposit_amount']);
    }

    public function test_two_package_uses_tier_for_combined_quantity(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', [
            'LELAKI' => 100,
            'PEREMPUAN' => 300,
        ]);

        $result = app(ResolvePackagePriceService::class)->resolve($order);

        $this->assertSame('PUTERI_MANIS_CARD_400', $result['catalog_item']->code);
        $this->assertSame(400, $result['card_quantity']);
        $this->assertSame('268.00', $result['amount']);
    }

    public function test_two_package_price_is_persisted_as_snapshot_and_order_item(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', [
            'LELAKI' => 100,
            'PEREMPUAN' => 300,
        ]);

        $snapshot = app(BuildOrderPricingService::class)->createSnapshot($order);

        $this->assertSame('268.00', $snapshot->subtotal);
        $this->assertSame('268.00', $snapshot->total_amount);
        $this->assertSame('100.00', $snapshot->booking_amount);
        $this->assertSame('268.00', $snapshot->outstanding_amount);
        $this->assertSame(400, $snapshot->calculation_snapshot['card_quantity']);
        $this->assertSame([
            'LELAKI' => 100,
            'PEREMPUAN' => 300,
        ], $snapshot->calculation_snapshot['side_quantities']);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_code' => 'PUTERI_MANIS_CARD_400',
            'line_total' => '268.00',
        ]);
    }

    public function test_special_two_package_combo_snapshot_keeps_approved_price(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', [
            'LELAKI' => 30,
            'PEREMPUAN' => 50,
        ]);

        $snapshot = app(BuildOrderPricingService::class)->createSnapshot($order);

        $this->assertSame('226.00', $snapshot->total_amount);
        $this->assertSame('100.00', $snapshot->booking_amount);
        $this->assertSame(
            'PUTERI_MANIS_2P_30_50',
            $snapshot->calculation_snapshot['catalog_item_code']
        );
    }

    public function test_puteri_manis_special_two_package_combo_costs_226(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', [
            'LELAKI' => 30,
            'PEREMPUAN' => 50,
        ]);

        $result = app(ResolvePackagePriceService::class)->resolve($order);

        $this->assertSame('PUTERI_MANIS_2P_30_50', $result['catalog_item']->code);
        $this->assertSame('226.00', $result['amount']);
    }

    public function test_jelata_special_two_package_combo_costs_118(): void
    {
        $order = $this->draftOrder('JELATA', [
            'LELAKI' => 30,
            'PEREMPUAN' => 50,
        ]);

        $result = app(ResolvePackagePriceService::class)->resolve($order);

        $this->assertSame('JELATA_2P_30_50', $result['catalog_item']->code);
        $this->assertSame('118.00', $result['amount']);
    }

    public function test_two_package_total_without_a_tier_is_rejected(): void
    {
        $order = $this->draftOrder('PUTERI_MANIS', [
            'LELAKI' => 30,
            'PEREMPUAN' => 50,
        ]);

        $order->packageSides->firstWhere('side', 'PEREMPUAN')->update([
            'card_quantity' => 30,
        ]);
        $order->forceFill(['card_quantity' => 60])->save();

        $this->expectException(ValidationException::class);

        app(ResolvePackagePriceService::class)->resolve(
            $order->fresh('packageSides')
        );
    }

    /**
     * @param array<string, int> $quantities
     */
    private function draftOrder(string $packageType, array $quantities): Order
    {
        $packageCount = count($quantities);
        $orderData = [
            'package_count' => $packageCount,
            'package_type' => $packageType,
        ];

        if ($packageCount === 1) {
            $orderData['side'] = array_key_first($quantities);
        } else {
            $orderData['package_format'] = 'SEPARATE';
            $orderData['first_event_side'] = 'LELAKI';
        }

        $order = app(CreateOrderService::class)->create($orderData);

        $sides = [];
        foreach ($quantities as $side => $quantity) {
            $sides[$side] = ['card_quantity' => $quantity];
        }

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => array_sum($quantities),
            'sides' => $sides,
        ]);

        return $order->fresh('packageSides');
    }
}
