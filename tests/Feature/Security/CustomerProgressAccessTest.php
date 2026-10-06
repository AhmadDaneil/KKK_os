<?php

namespace Tests\Feature\Security;

use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProgressAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_lookup_requires_a_valid_order_id(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Selamat',
            'customer_email' => 'pelanggan@example.test',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY']);

        $this->from(route('public.orders.progress'))
            ->post(route('public.orders.progress.lookup'), [
                'order_id' => 'KKK-TIDAK-WUJUD',
            ])
            ->assertRedirect(route('public.orders.progress'))
            ->assertSessionHasErrors('order_id');

        $this->get(route('orders.artwork.review', $order->order_id))
            ->assertNotFound();
    }

    public function test_progress_lookup_grants_access_with_order_id(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Sah',
            'customer_email' => 'sah@example.test',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])->assertOk();

        $this->get(route('orders.artwork.review', $order->order_id))
            ->assertOk();
    }
}
