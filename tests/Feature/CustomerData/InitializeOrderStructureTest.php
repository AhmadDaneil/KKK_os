<?php

namespace Tests\Feature\CustomerData;

use App\Models\Order;
use App\Services\Orders\InitializeOrderStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitializeOrderStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_creates_one_selected_side_and_three_contacts(): void
    {
        $order = Order::create([
            'order_id' => 'KKK-260909-9001',
            'package_count' => 1,
            'status' => 'DETAILS_INCOMPLETE',
            'booking_payment_status' => 'TEST',
        ]);

        $order = app(InitializeOrderStructureService::class)->initialize($order, 'LELAKI');

        $this->assertCount(1, $order->packageSides);
        $this->assertSame('LELAKI', $order->packageSides->first()->side);
        $this->assertCount(3, $order->packageSides->first()->event->contacts);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_two_packages_create_both_sides_but_still_one_business_order(): void
    {
        $order = Order::create([
            'order_id' => 'KKK-260909-9002',
            'package_count' => 2,
            'status' => 'DETAILS_INCOMPLETE',
            'booking_payment_status' => 'TEST',
        ]);

        $order = app(InitializeOrderStructureService::class)->initialize($order);

        $this->assertCount(2, $order->packageSides);
        $this->assertEqualsCanonicalizing(['LELAKI', 'PEREMPUAN'], $order->packageSides->pluck('side')->all());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_package_sides', 2);
        $this->assertDatabaseCount('order_events', 2);
        $this->assertDatabaseCount('event_contacts', 6);
    }
}
