<?php

namespace Tests\Feature\Security;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_magic_link_bootstraps_session_and_redirects_to_clean_url(): void
    {
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'customer_name' => 'Stage 10C Test']);
        $link = app(GenerateOrderAccessLinkService::class)->generate($order);
        $response = $this->get($this->requestUri($link));
        $response->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));
        $this->assertStringNotContainsString('token=', (string) $response->headers->get('Location'));
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertOk()->assertDontSee('token=');
    }

    public function test_two_package_magic_link_bootstraps_session_and_keeps_clean_dashboard_access(): void
    {
        $order = app(CreateOrderService::class)->create(['package_count' => 2, 'customer_name' => 'Stage 10C Two Package']);
        $link = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($this->requestUri($link))->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertOk()->assertDontSee('token=');
    }

    public function test_clean_dashboard_without_authorized_session_is_blocked(): void
    {
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'PEREMPUAN']);
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertNotFound();
    }

    public function test_session_for_one_order_cannot_access_another_order(): void
    {
        $orderA = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI']);
        $orderB = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'PEREMPUAN']);
        $linkA = app(GenerateOrderAccessLinkService::class)->generate($orderA);
        $this->get($this->requestUri($linkA))->assertRedirect(route('orders.dashboard', ['orderId' => $orderA->order_id]));
        $this->get(route('orders.dashboard', ['orderId' => $orderB->order_id]))->assertNotFound();
    }

    public function test_invalid_initial_token_is_blocked(): void
    {
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI']);
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id, 'token' => 'invalid-token']))->assertNotFound();
    }

    public function test_expired_initial_token_is_forbidden(): void
    {
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI']);
        $link = app(GenerateOrderAccessLinkService::class)->generate($order, -1);
        $this->get($this->requestUri($link))->assertForbidden();
    }

    private function requestUri(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        return $query === '' ? $path : $path.'?'.$query;
    }
}
