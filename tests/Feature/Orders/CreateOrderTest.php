<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_creates_exactly_one_business_order(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Test Lelaki',
            'customer_email' => 'TEST@EXAMPLE.COM',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $order->package_count);
        $this->assertSame('test@example.com', $order->customer_email);
        $this->assertSame('DETAILS_INCOMPLETE', $order->status);
    }

    public function test_two_packages_still_create_exactly_one_business_order(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'Test Dua Pakej',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $order->package_count);
    }
    public function test_one_package_lelaki_initializes_correct_structure(): void
{
    $service = app(\App\Services\Orders\CreateOrderService::class);

    $order = $service->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $this->assertDatabaseCount('orders', 1);

    $this->assertDatabaseHas('order_package_sides', [
        'order_id' => $order->id,
        'side' => 'LELAKI',
    ]);

    $this->assertDatabaseCount('order_package_sides', 1);
    $this->assertDatabaseCount('order_events', 1);
    $this->assertDatabaseCount('event_contacts', 3);
    }
    public function test_one_package_perempuan_initializes_correct_structure(): void
{
    $service = app(\App\Services\Orders\CreateOrderService::class);

    $order = $service->create([
        'package_count' => 1,
        'side' => 'PEREMPUAN',
        'customer_name' => 'Test Perempuan',
    ]);

    $this->assertDatabaseHas('order_package_sides', [
        'order_id' => $order->id,
        'side' => 'PEREMPUAN',
    ]);
    }
    public function test_two_packages_initialize_both_sides_automatically(): void
{
    $service = app(\App\Services\Orders\CreateOrderService::class);

    $order = $service->create([
        'package_count' => 2,
        'customer_name' => 'Test Dua Pakej',
    ]);

    $this->assertDatabaseCount('orders', 1);

    $this->assertDatabaseHas('order_package_sides', [
        'order_id' => $order->id,
        'side' => 'LELAKI',
    ]);

    $this->assertDatabaseHas('order_package_sides', [
        'order_id' => $order->id,
        'side' => 'PEREMPUAN',
    ]);

    $this->assertDatabaseCount('order_package_sides', 2);
    $this->assertDatabaseCount('order_events', 2);
    $this->assertDatabaseCount('event_contacts', 6);
    }
    public function test_one_package_requires_a_side(): void
    {
    $this->expectException(\InvalidArgumentException::class);

    app(\App\Services\Orders\CreateOrderService::class)->create([
        'package_count' => 1,
        'customer_name' => 'Missing Side',
    ]);
    }
}
