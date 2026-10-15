<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $this->get(route('orders.review.show', ['orderId' => $order->order_id]))
            ->assertOk()
            ->assertSee('Bayaran Deposit')
            ->assertSee('RM 188.00')
            ->assertSee('RM 50.00')
            ->assertSee('Booking telah dibayar dan disahkan.')
            ->assertDontSee('deposit_receipt', false)
            ->assertSee('post_confirmation_liability_acknowledged', false);

        $response = $this->post(route('orders.confirm.store', [
            'orderId' => $order->order_id,
        ]), [
            'responsibility_acknowledged' => '1',
            'post_confirmation_liability_acknowledged' => '1',
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
            ->assertSee(route('public.orders.progress', [
                'order_id' => $order->order_id,
            ]), false);

        $payment = $order->fresh()->payments()->where('payment_type', 'BOOKING_DEPOSIT')->firstOrFail();
        $this->assertSame('PAID', $payment->status);
        $this->assertSame('BILLPLZ', $payment->provider);
        $this->assertSame('50.00', $payment->amount);
        $this->assertSame('MYR', $payment->currency);
        $this->assertDatabaseHas('order_pricing_snapshots', [
            'order_id' => $order->id,
            'subtotal' => '188.00',
            'total_amount' => '188.00',
            'booking_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_code' => 'PUTERI_MANIS_CARD_200',
            'line_total' => '188.00',
        ]);
        $this->assertSame('PAID', $order->fresh()->booking_payment_status);
        $this->assertTrue($order->fresh()->confirmation->confirmed_snapshot['post_confirmation_liability_acknowledged']);
        $this->assertSame('LIVE_PREVIEW_REFERENCE', $order->fresh()->confirmation->confirmed_snapshot['snapshot_type']);
    }

    public function test_confirmation_requires_both_acknowledgements(): void
    {
        $order = $this->completeOrder();
        $this->establishCustomerSession($order);

        $this->from(route('orders.review.show', ['orderId' => $order->order_id]))
            ->post(route('orders.confirm.store', ['orderId' => $order->order_id]), [])
            ->assertRedirect(route('orders.review.show', ['orderId' => $order->order_id]))
            ->assertSessionHasErrors([
                'responsibility_acknowledged',
                'post_confirmation_liability_acknowledged',
            ]);

        $this->assertSame('DETAILS_INCOMPLETE', $order->fresh()->status);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_unpaid_booking_can_resume_online_payment_from_final_review(): void
    {
        $order = $this->completeOrder();
        $order->payments()->delete();
        $order->update(['booking_payment_status' => 'PENDING']);
        $this->establishCustomerSession($order);

        config()->set('services.billplz.enabled', true);
        config()->set('app.url', 'https://167.71.196.30');
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'resumed-booking',
            'collection_id' => 'sandbox-collection',
            'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/resumed-booking',
        ])]);

        $this->get(route('orders.review.show', ['orderId' => $order->order_id]))
            ->assertOk()
            ->assertSee('Booking belum dibayar.')
            ->assertSee('Teruskan Pembayaran RM 50.00')
            ->assertDontSee('Booking telah dibayar dan disahkan.');

        $this->post(route('orders.booking-payment.store', ['orderId' => $order->order_id]))
            ->assertRedirect('https://www.billplz-sandbox.com/bills/resumed-booking');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'amount' => '50.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_legacy_order_id_url_redirects_to_final_review(): void
    {
        $order = $this->completeOrder();
        $this->establishCustomerSession($order);

        $this->get(route('orders.order-id.legacy', ['orderId' => $order->order_id]))
            ->assertRedirect(route('orders.review.show', ['orderId' => $order->order_id]));
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
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Customer Thank You',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'card_quantity' => 200,
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

        $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'provider_reference' => 'paid-booking-'.$order->id,
            'amount' => '50.00',
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);
        $order->update(['booking_payment_status' => 'PAID']);

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
