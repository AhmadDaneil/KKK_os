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

    public function test_design_ready_dashboard_shows_artwork_review_cta(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $order->update(['status' => 'DESIGN_READY']);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $this->get($url)->assertRedirect();

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Semak Artwork')
        ->assertSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
}

public function test_design_in_progress_dashboard_does_not_show_artwork_review_cta(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $order->update(['status' => 'DESIGN_IN_PROGRESS']);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $this->get($url)->assertRedirect();

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertDontSee('Semak Artwork')
        ->assertDontSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
}

public function test_correction_requested_dashboard_shows_artwork_status_cta(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $order->update(['status' => 'CORRECTION_REQUESTED']);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $this->get($url)->assertRedirect();

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Lihat Status Artwork')
        ->assertSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
}

    public function test_design_approved_dashboard_shows_artwork_history_cta(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Test Customer',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        $url = app(GenerateOrderAccessLinkService::class)->generate($order);

        $this->get($url)->assertRedirect();

        $this->get(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
            ->assertOk()
            ->assertSee('Lihat Artwork')
            ->assertSee(route('orders.artwork.review', [
                'orderId' => $order->order_id,
            ]), false);
    }

    public function test_details_confirmed_dashboard_shows_artwork_waiting_state_without_review_cta(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $order->update(['status' => 'DETAILS_CONFIRMED']);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $this->get($url)->assertRedirect();

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Artwork Tempahan')
        ->assertSee('Menunggu proses design')
        ->assertSee('35%')
        ->assertSee('data-progress-tone="red"', false)
        ->assertDontSee('Semak Artwork')
        ->assertDontSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
    }

    public function test_ready_for_design_dashboard_shows_waiting_state_without_review_cta(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Test Customer',
    ]);

    $order->update(['status' => 'READY_FOR_DESIGN']);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $this->get($url)->assertRedirect();

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Artwork Tempahan')
        ->assertSee('Menunggu designer')
        ->assertSee('40%')
        ->assertSee('data-progress-tone="yellow"', false)
        ->assertDontSee('Semak Artwork')
        ->assertDontSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
}

}
