<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_ids_are_unique_for_many_orders(): void
    {
        $service = app(CreateOrderService::class);

        $ids = [];

        for ($i = 0; $i < 100; $i++) {
            $order = $service->create([
                'package_count' => 1,
                'side' => 'LELAKI',
            ]);

            $ids[] = $order->order_id;
        }

        $this->assertCount(100, array_unique($ids));
        $this->assertDatabaseCount('orders', 100);
    }
}