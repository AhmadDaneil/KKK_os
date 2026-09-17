<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\Orders\CreateOrderService;
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
            ->assertSee(route('public.orders.create'))
            ->assertSee(route('public.orders.progress'));
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

    public function test_unknown_order_id_returns_a_clear_error(): void
    {
        $this->from(route('public.orders.progress'))
            ->post(route('public.orders.progress.lookup'), [
                'order_id' => 'KKK-260917-9999',
            ])
            ->assertRedirect(route('public.orders.progress'))
            ->assertSessionHasErrors('order_id');
    }
}
