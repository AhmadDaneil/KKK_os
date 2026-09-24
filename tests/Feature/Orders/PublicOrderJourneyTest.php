<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_links_to_order_and_progress_flows(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('KingKadKahwin')
            ->assertSee('Tempah Sekarang')
            ->assertSee('Ikuti kami')
            ->assertSee('Instagram')
            ->assertSee('Facebook')
            ->assertSee('WhatsApp')
            ->assertSee(route('public.orders.create'))
            ->assertSee(route('public.orders.progress'));
    }

    public function test_progress_page_has_a_back_button_to_the_landing_page(): void
    {
        $this->get(route('public.orders.progress'))
            ->assertOk()
            ->assertSee('Kembali')
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_customer_can_start_an_order_and_reach_full_form(): void
    {
        $response = $this->post(route('public.orders.store'), [
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Baharu',
            'customer_email' => 'pelanggan@example.com',
            'customer_phone' => '0123456789',
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect();
        $this->assertStringContainsString('/order/'.$order->order_id, $response->headers->get('Location'));
        $this->assertStringContainsString('token=', $response->headers->get('Location'));
        $this->assertSame('Pelanggan Baharu', $order->customer_name);
        $this->assertCount(1, $order->packageSides);
    }

    public function test_customer_can_create_folded_two_package_order_with_bride_side_first(): void
    {
        $response = $this->post(route('public.orders.store'), [
            'package_count' => 2,
            'package_format' => 'FOLDED',
            'first_event_side' => 'PEREMPUAN',
            'customer_name' => 'Dua Majlis',
            'customer_email' => 'dua@example.com',
            'customer_phone' => '0122222222',
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect();
        $this->assertSame(2, $order->package_count);
        $this->assertSame('FOLDED', $order->package_format);
        $this->assertSame('PEREMPUAN', $order->first_event_side);
        $this->assertEqualsCanonicalizing(['LELAKI', 'PEREMPUAN'], $order->packageSides->pluck('side')->all());
    }

    public function test_progress_lookup_shows_public_status_without_customer_details(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Nama Sulit',
            'customer_email' => 'sulit@example.com',
            'customer_phone' => '0199999999',
        ]);

        $order->update(['status' => 'DESIGN_IN_PROGRESS']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => strtolower($order->order_id),
        ])
            ->assertOk()
            ->assertSee($order->order_id)
            ->assertSee('50%')
            ->assertSee('Design Sedang Disediakan')
            ->assertDontSee('Nama Sulit')
            ->assertDontSee('sulit@example.com')
            ->assertDontSee('0199999999');
    }

    public function test_printed_order_shows_completed_printing_progress(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);
        $order->update(['status' => 'PRINTED']);

        $this->post(route('public.orders.progress.lookup'), ['order_id' => $order->order_id])
            ->assertOk()
            ->assertSee('88%')
            ->assertSee('Cetakan Selesai')
            ->assertSee('Cetakan tempahan anda telah siap dan akan diteruskan ke proses pembungkusan.');
    }

    public function test_unknown_order_id_returns_a_clear_error(): void
    {
        $this->from(route('public.orders.progress'))
            ->post(route('public.orders.progress.lookup'), [
                'order_id' => 'KKK-260917-9999',
            ])
            ->assertRedirect(route('public.orders.progress'))
            ->assertSessionHasErrors('order_id');
    }

    public function test_progress_page_links_to_artwork_review_for_authorized_customer(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Artwork',
            'customer_email' => 'artwork@example.com',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY']);

        $secureLink = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($secureLink)->assertRedirect(route('orders.dashboard', $order->order_id));

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('Buka Semakan Penuh')
            ->assertSee(route('orders.artwork.review', $order->order_id));
    }

    public function test_order_id_lookup_grants_direct_artwork_review_access(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Artwork',
            'customer_email' => 'artwork@example.com',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('Buka Semakan Penuh')
            ->assertSee(route('orders.artwork.review', $order->order_id));

        $this->get(route('orders.artwork.review', $order->order_id))->assertOk();
    }
}
