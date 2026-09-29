<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
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
        ->assertSee('Pratonton Kad Langsung')
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
        ->assertSee('Semak Hasil Reka Bentuk')
        ->assertSee(route('orders.artwork.review', [
            'orderId' => $order->order_id,
        ]), false);
}

public function test_two_package_dashboard_orders_majlis_and_keeps_designs_separate(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 2,
        'package_format' => 'SEPARATE',
        'first_event_side' => 'PEREMPUAN',
        'customer_name' => 'Two Events',
    ]);
    $this->get(app(GenerateOrderAccessLinkService::class)->generate($order));

    $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
        ->assertOk()
        ->assertSee('Pakej Berasingan')
        ->assertSee('Pakej Gabungan – Kad Lipatan')
        ->assertSee('Majlis 1 – Pihak Perempuan')
        ->assertSee('Majlis 2 – Pihak Lelaki')
        ->assertSee('Ibu Bapa Pengantin Perempuan')
        ->assertSee('Ibu Bapa Pengantin Lelaki')
        ->assertSee('data-folded-second-design="true"', false)
        ->assertSee('data-preview-field="card_title_jawi"', false);
}

public function test_folded_card_preview_uses_the_first_event_for_front_and_orders_the_back_pages(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 2,
        'package_format' => 'FOLDED',
        'first_event_side' => 'PEREMPUAN',
        'customer_name' => 'Folded Card Customer',
    ]);

    $this->get(app(GenerateOrderAccessLinkService::class)->generate($order));

    $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
        ->assertOk()
        ->assertSee('data-folded-card-preview="true"', false)
        ->assertSee('Halaman 1 · Kad Depan')
        ->assertSee('Halaman 2 · Kad Belakang · Perempuan')
        ->assertSee('Halaman 3 · Kad Belakang · Lelaki')
        ->assertSee('data-preview-folded-side="PEREMPUAN" data-preview-folded-face="front"', false)
        ->assertSee('data-preview-folded-side="PEREMPUAN" data-preview-folded-face="back"', false)
        ->assertSee('data-preview-folded-side="LELAKI" data-preview-folded-face="back"', false)
        ->assertSee('name="sides[PEREMPUAN][design][design_code]"', false)
        ->assertSee('data-folded-second-design="true"', false)
        ->assertSee('@kingkadkahwin');
}

public function test_folded_card_reuses_the_first_majlis_design_for_the_second_back_page(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 2,
        'package_format' => 'FOLDED',
        'first_event_side' => 'PEREMPUAN',
    ]);

    app(SaveOrderDraftService::class)->save($order, [
        'package_format' => 'FOLDED',
        'first_event_side' => 'PEREMPUAN',
        'sides' => [
            'PEREMPUAN' => [
                'design' => [
                    'theme' => 'GARDEN',
                    'design_code' => 'FOLD-101',
                    'card_title' => 'Majlis Perkahwinan',
                ],
            ],
        ],
    ]);

    $order->refresh()->load('packageSides.design');

    $this->assertSame(
        'FOLD-101',
        $order->packageSides->firstWhere('side', 'LELAKI')->design->design_code
    );
    $this->assertSame(
        'GARDEN',
        $order->packageSides->firstWhere('side', 'LELAKI')->design->theme
    );
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
        ->assertDontSee('Semak Hasil Reka Bentuk')
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
        ->assertSee('Lihat Status Hasil Reka Bentuk')
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
            ->assertSee('Lihat Hasil Reka Bentuk')
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
        ->assertSee('Hasil Reka Bentuk Tempahan')
        ->assertSee('Menunggu proses reka bentuk')
        ->assertSee('35%')
        ->assertSee('data-progress-tone="red"', false)
        ->assertDontSee('Semak Hasil Reka Bentuk')
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
            ->assertSee('Hasil Reka Bentuk Tempahan')
            ->assertSee('Menunggu Proses Reka Bentuk')
            ->assertSee('40%')
            ->assertSee('data-progress-tone="yellow"', false)
            ->assertDontSee('Semak Hasil Reka Bentuk')
            ->assertDontSee(route('orders.artwork.review', [
                'orderId' => $order->order_id,
            ]), false);
    }

}
