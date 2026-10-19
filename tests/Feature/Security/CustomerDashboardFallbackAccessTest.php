<?php

namespace Tests\Feature\Security;

use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardFallbackAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_link_prefills_the_order_id_on_the_branded_access_page(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '60123456789',
        ]);

        $this->get(route('orders.access.create', ['order_id' => $order->order_id]))
            ->assertSee('Access Your Order Dashboard')
            ->assertSee('KingKadKahwin')
            ->assertSee('class="brand-customer-page"', false)
            ->assertSee('brand-order-access', false)
            ->assertSee('css/landing.css', false)
            ->assertSee('css/customer-theme.css', false)
            ->assertSee('css/customer-service.css', false)
            ->assertSee('css/customer-brand.css', false)
            ->assertSee('Back to Home')
            ->assertSee('Track Progress')
            ->assertSee('value="'.$order->order_id.'"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('validation.regex');
    }

    public function test_customer_can_establish_dashboard_session_with_order_id_and_normalized_phone(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'package_type' => 'PUTERI_MANIS',
            'package_format' => 'SEPARATE',
            'first_event_side' => 'LELAKI',
            'customer_phone' => '60123456789',
        ]);
        $order->update(['booking_payment_status' => 'PAID']);

        $this->post(route('orders.access.store'), [
            'customer_reference' => strtolower($order->order_id),
            'customer_phone' => '012-345 6789',
        ])->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
            ->assertSee('Order Progress', false);
        $this->assertDatabaseHas('customer_access_logs', [
            'order_id' => $order->id,
            'attempted_reference' => $order->order_id,
            'result' => 'SUCCESS',
        ]);
    }

    public function test_invalid_order_identifier_shows_an_english_validation_message(): void
    {
        $this->from(route('orders.access.create'))
            ->post(route('orders.access.store'), [
                'customer_reference' => 'BUKAN-ID',
                'customer_phone' => '0123456789',
            ])
            ->assertRedirect(route('orders.access.create'))
            ->assertSessionHasErrors([
                'customer_reference' => 'The order ID or customer reference format is invalid.',
            ]);
    }

    public function test_customer_can_establish_dashboard_session_with_reference_and_normalized_phone(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Fallback Customer',
            'customer_phone' => '60123456789',
        ]);
        $order->update(['customer_reference' => 'KKK261014-001']);
        $order->update(['booking_payment_status' => 'PAID']);

        $this->post(route('orders.access.store'), [
            'customer_reference' => 'kkk261014-001',
            'customer_phone' => '012-345 6789',
        ])->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertOk();
        $this->assertDatabaseHas('customer_access_logs', [
            'order_id' => $order->id,
            'result' => 'SUCCESS',
        ]);
        $this->assertDatabaseMissing('customer_access_logs', ['phone_fingerprint' => '60123456789']);
    }

    public function test_wrong_phone_is_generic_audited_and_temporarily_locked(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Fallback Customer',
            'customer_phone' => '60123456789',
        ]);
        $order->update(['customer_reference' => 'KKK261014-002']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('orders.access.store'), [
                'customer_reference' => 'KKK261014-002',
                'customer_phone' => '0199999999',
            ])->assertSessionHasErrors('customer_reference');
        }

        $this->post(route('orders.access.store'), [
            'customer_reference' => 'KKK261014-002',
            'customer_phone' => '0123456789',
        ])->assertSessionHasErrors('customer_reference');

        $this->assertDatabaseCount('customer_access_logs', 6);
        $this->assertDatabaseHas('customer_access_logs', ['result' => 'LOCKED']);
    }
}
