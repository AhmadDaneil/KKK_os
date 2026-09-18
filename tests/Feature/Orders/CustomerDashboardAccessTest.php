<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\FulfilmentJob;
use App\Models\PackingJob;

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
        ->assertSee('Live Preview')
        ->assertSee('Kad 4 × 6')
        ->assertSee('data-card-preview="LELAKI"', false)
        ->assertSee('data-preview-face-target="front"', false)
        ->assertSee('data-preview-face-target="back"', false)
        ->assertSee('data-preview-field="card_title_jawi"', false)
        ->assertSee('وليمة العروس')
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


        public function test_completed_courier_tracking_is_available_to_authorized_customer_dashboard(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Courier Customer',
        ]);

        $order->update(['status' => 'COMPLETED']);

        $packingJob = PackingJob::query()->create([
            'order_id' => $order->id,
            'status' => 'PACKED',
        ]);

        $fulfilmentJob = FulfilmentJob::query()->create([
            'order_id' => $order->id,
            'order_fulfilment_id' => $order->fulfilment->id,
            'packing_job_id' => $packingJob->id,
            'method' => 'COURIER',
            'status' => 'COMPLETED',
            'courier_provider' => 'Pos Laju',
            'tracking_number' => 'PL123456789MY',
            'shipped_at' => now(),
        ]);

        $url = app(GenerateOrderAccessLinkService::class)->generate($order);

        $this->get($url)->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

        $this->get(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
            ->assertOk()
            ->assertViewHas('order', function ($dashboardOrder) use ($order, $fulfilmentJob) {
                return $dashboardOrder->order_id === $order->order_id
                    && $dashboardOrder->status === 'COMPLETED'
                    && $dashboardOrder->relationLoaded('fulfilmentJob')
                    && $dashboardOrder->fulfilmentJob !== null
                    && $dashboardOrder->fulfilmentJob->id === $fulfilmentJob->id
                    && $dashboardOrder->fulfilmentJob->method === 'COURIER'
                    && $dashboardOrder->fulfilmentJob->status === 'COMPLETED'
                    && $dashboardOrder->fulfilmentJob->courier_provider === 'Pos Laju'
                    && $dashboardOrder->fulfilmentJob->tracking_number === 'PL123456789MY'
                    && $dashboardOrder->fulfilmentJob->shipped_at !== null;
            })
            ->assertViewHas('progress', function ($progress) {
                return $progress['percentage'] === 100
                    && $progress['label'] === 'Tempahan Selesai';
            })
            ->assertSee('Maklumat Penghantaran')
            ->assertSee('Pos Laju')
            ->assertSee('PL123456789MY');
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
