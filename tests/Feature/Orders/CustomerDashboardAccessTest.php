<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_opens_correct_dashboard(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $response = $this->get($url);

    $response->assertRedirect(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]));

    $this->assertStringNotContainsString(
        'token=',
        (string) $response->headers->get('Location')
    );

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee($order->order_id)
        ->assertDontSee('token=');
}

public function test_invalid_token_is_rejected(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
        'token' => 'invalid-token',
    ]))
        ->assertNotFound();
    }
}
