<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicOrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        config()->set('app.url', 'https://167.71.196.30');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
        Http::fake(['www.billplz-sandbox.com/*' => function ($request) {
            if (config('services.billplz.test_reject_create')) {
                return Http::response(['error' => ['type' => 'InvalidCollection']], 422);
            }

            $amount = (int) $request->data()['amount'];

            return Http::response([
                'id' => 'public-bill-'.uniqid(),
                'collection_id' => 'sandbox-collection',
                'amount' => $amount,
                'url' => 'https://www.billplz-sandbox.com/bills/public-checkout',
            ]);
        }]);
    }

    public function test_landing_page_links_to_order_and_progress_flows(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('KingKadKahwin')
            ->assertSee('Chat to Book')
            ->assertSee('Follow us')
            ->assertSee('Instagram')
            ->assertSee('Facebook')
            ->assertSee('WhatsApp')
            ->assertSee(route('public.orders.create'))
            ->assertSee(route('public.orders.progress'));
    }

    public function test_landing_page_displays_all_four_animated_card_examples(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('images/landing/card-nostalgia-ckn-008.jpg', false)
            ->assertSee('images/landing/card-desa-ckd-008.jpg', false)
            ->assertSee('images/landing/card-garden-purple.png', false)
            ->assertSee('images/landing/card-islamic-gold.png', false)
            ->assertSee('card-runner-garden', false)
            ->assertSee('card-runner-islamic', false);
    }

    public function test_landing_page_renders_social_icons_and_routes_customer_service_through_wabot(): void
    {
        config()->set('kingkadkahwin.social.instagram', 'https://instagram.example/kkk');
        config()->set('kingkadkahwin.social.facebook', 'https://facebook.example/kkk');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('social-instagram', false)
            ->assertSee('social-facebook', false)
            ->assertDontSee('social-whatsapp', false)
            ->assertSee('customer-service-button', false)
            ->assertSee(route('public.orders.create', ['purpose' => 'support']), false)
            ->assertSee('<svg', false);
    }

    public function test_progress_page_has_a_back_button_to_the_landing_page(): void
    {
        $this->get(route('public.orders.progress'))
            ->assertOk()
            ->assertSee('Back')
            ->assertSee('header-link nav-progress', false)
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_order_cta_goes_to_whatsapp_before_any_order_is_created(): void
    {
        $this->get(route('public.orders.create'))
            ->assertRedirectContains('https://wa.me/601165656968');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_new_sessions_are_shared_evenly_across_three_numbers_and_repeat_clicks_stay_put(): void
    {
        $numbers = ['601165656968', '60177197596', '601135623239'];

        foreach ($numbers as $number) {
            $this->get(route('public.orders.create'))->assertRedirectContains('https://wa.me/'.$number);
            $this->get(route('public.orders.create', ['purpose' => 'support']))
                ->assertRedirectContains('https://wa.me/'.$number);
            $this->flushSession();
        }

        $this->get(route('public.orders.create'))
            ->assertRedirectContains('https://wa.me/'.$numbers[0]);
        $this->assertDatabaseHas('wabot_route_counters', ['id' => 1, 'next_index' => 1]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unpaid_draft_cannot_be_opened_from_the_landing_page(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Draft Resume Customer',
            'customer_email' => 'draft@example.com',
            'customer_phone' => '0123456789',
        ]);

        $this->get(route('public.orders.create'))
            ->assertRedirectContains('https://wa.me/');
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertNotFound();
    }

    public function test_public_order_form_can_no_longer_create_a_booking(): void
    {
        $this->post('/tempah', [
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Baharu',
            'customer_email' => 'pelanggan@example.com',
            'customer_phone' => '0123456789',
        ])->assertStatus(405);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_order_form_cannot_create_two_package_booking(): void
    {
        $this->post('/tempah', [
            'package_count' => 2,
            'package_type' => 'PUTERI_MANIS',
            'package_format' => 'FOLDED',
            'first_event_side' => 'PEREMPUAN',
            'customer_name' => 'Dua Majlis',
            'customer_email' => 'dua@example.com',
            'customer_phone' => '0122222222',
        ])->assertStatus(405);

        $this->assertDatabaseCount('orders', 0);
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

        $order->update(['status' => 'DESIGN_IN_PROGRESS', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => strtolower($order->order_id),
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertSee($order->order_id)
            ->assertSee('50%')
            ->assertSee('Design in Progress')
            ->assertDontSee('Nama Sulit')
            ->assertDontSee('sulit@example.com')
            ->assertDontSee('0199999999');
    }

    public function test_approved_design_progress_hides_redundant_status_copy(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_APPROVED', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('70%')
            ->assertSee('Balance payment after design approval')
            ->assertDontSee('Hasil Design Diluluskan')
            ->assertDontSee('The design has been approved internally.');
    }

    public function test_printed_order_shows_completed_printing_progress(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'PRINTED', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertSee('88%')
            ->assertSee('Printing Completed')
            ->assertSee('Printing is complete and your order will proceed to packing.');
    }

    public function test_printing_order_shows_progress_button_without_granting_private_access(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'PRINTING', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('View progress')
            ->assertSee('href="'.route('orders.access.create', ['order_id' => $order->order_id]).'"', false);

        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
            ->assertNotFound();
    }

    public function test_customer_with_an_active_two_package_session_can_open_progress_directly(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'package_type' => 'PUTERI_MANIS',
            'package_format' => 'SEPARATE',
            'first_event_side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'COMPLETED', 'booking_payment_status' => 'PAID']);

        $secureLink = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($secureLink)
            ->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('View progress')
            ->assertSee('href="'.route('orders.dashboard', ['orderId' => $order->order_id]).'"', false);
    }

    public function test_unknown_order_id_returns_a_clear_error(): void
    {
        $this->from(route('public.orders.progress'))
            ->post(route('public.orders.progress.lookup'), [
                'order_id' => 'KKK-260917-9999',
                'customer_phone' => '0123456789',
            ])
            ->assertRedirect(route('public.orders.progress'))
            ->assertSessionHasErrors('order_id');
    }

    public function test_public_progress_does_not_expose_private_artwork_link(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Artwork',
            'customer_email' => 'artwork@example.com',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY', 'booking_payment_status' => 'PAID']);

        $secureLink = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($secureLink)->assertRedirect(route('orders.dashboard', $order->order_id));

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertDontSee('Open Full Review');
    }

    public function test_order_id_lookup_does_not_grant_direct_artwork_review_access(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Artwork',
            'customer_email' => 'artwork@example.com',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertDontSee('Open Full Review');

        $this->get(route('orders.artwork.review', $order->order_id))->assertNotFound();
    }

    public function test_progress_lookup_does_not_open_private_order_with_order_id_only(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Selamat',
            'customer_email' => 'selamat@example.com',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'DESIGN_READY', 'booking_payment_status' => 'PAID']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])->assertOk();

        $this->get(route('orders.artwork.review', $order->order_id))->assertNotFound();
    }
}
