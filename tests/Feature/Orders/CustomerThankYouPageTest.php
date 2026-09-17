<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerThankYouPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_form_supports_autosave_json_requests(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Autosave Customer',
        ]);
        $this->establishCustomerSession($order);

        $this->postJson(route('orders.draft.update', [
            'orderId' => $order->order_id,
        ]), [
            'couple' => [
                'groom_name' => 'Nama Autosave',
            ],
        ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertDatabaseHas('order_couples', [
            'order_id' => $order->id,
            'couple_number' => 1,
            'groom_name' => 'Nama Autosave',
        ]);
    }

    public function test_successful_confirmation_redirects_to_thank_you_page(): void
    {
        $order = $this->completeOrder();
        $this->establishCustomerSession($order);

        $response = $this->post(route('orders.confirm.store', [
            'orderId' => $order->order_id,
        ]), [
            'responsibility_acknowledged' => '1',
        ]);

        $response->assertRedirect(route('orders.thank-you.show', [
            'orderId' => $order->order_id,
        ]));

        $this->get(route('orders.thank-you.show', [
            'orderId' => $order->order_id,
        ]))
            ->assertOk()
            ->assertSee('Terima Kasih')
            ->assertSee('Maklumat Tempahan')
            ->assertSee($order->order_id)
            ->assertSee('Customer Thank You')
            ->assertSee('Muhammad Syafiq')
            ->assertSee('Nur Awanis')
            ->assertSee('Semak Progress')
            ->assertSee(route('orders.dashboard', [
                'orderId' => $order->order_id,
            ]), false);
    }

    public function test_unconfirmed_order_is_redirected_away_from_thank_you_page(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Belum Disahkan',
        ]);
        $this->establishCustomerSession($order);

        $this->get(route('orders.thank-you.show', [
            'orderId' => $order->order_id,
        ]))->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));
    }

    public function test_thank_you_page_requires_authorized_customer_session(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Session Guard',
        ]);
        $order->forceFill(['details_confirmed_at' => now()])->save();

        $this->get(route('orders.thank-you.show', [
            'orderId' => $order->order_id,
        ]))->assertNotFound();
    }

    private function completeOrder(): Order
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Thank You',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => ['design_code' => 'A101'],
                    'parents' => [
                        'father_name' => 'Abdullah bin Ali',
                        'mother_name' => 'Fatimah binti Omar',
                    ],
                    'event' => [
                        'event_date' => '2026-12-20',
                        'meal_time' => '12:00',
                        'venue_name' => 'Dewan KKK',
                        'full_address' => 'Kuala Lumpur',
                        'contacts' => [
                            1 => ['contact_name' => 'Ahmad', 'contact_phone' => '0123456789'],
                            2 => ['contact_name' => 'Ali', 'contact_phone' => '0133456789'],
                            3 => ['contact_name' => 'Abu', 'contact_phone' => '0143456789'],
                        ],
                    ],
                ],
            ],
            'fulfilment' => ['method' => 'PICKUP'],
        ]);

        return $order->fresh();
    }

    private function establishCustomerSession(Order $order): void
    {
        $url = app(GenerateOrderAccessLinkService::class)->generate($order);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $this->get($query === '' ? $path : $path.'?'.$query)->assertRedirect();
    }
}
