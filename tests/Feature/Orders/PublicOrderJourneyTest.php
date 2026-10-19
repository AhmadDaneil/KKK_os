<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
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
            ->assertSee('Tempah Sekarang')
            ->assertSee('Ikuti kami')
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

    public function test_landing_page_renders_social_icons_and_uses_whatsapp_for_customer_service_only(): void
    {
        config()->set('kingkadkahwin.social.instagram', 'https://instagram.example/kkk');
        config()->set('kingkadkahwin.social.facebook', 'https://facebook.example/kkk');
        config()->set('kingkadkahwin.social.whatsapp', 'https://wa.example/kkk');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('social-instagram', false)
            ->assertSee('social-facebook', false)
            ->assertDontSee('social-whatsapp', false)
            ->assertSee('customer-service-button', false)
            ->assertSee('https://wa.example/kkk?text=', false)
            ->assertSee('<svg', false);
    }

    public function test_progress_page_has_a_back_button_to_the_landing_page(): void
    {
        $this->get(route('public.orders.progress'))
            ->assertOk()
            ->assertSee('Kembali')
            ->assertSee('header-link nav-progress', false)
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_start_order_header_has_back_and_progress_buttons_with_landing_hover_style(): void
    {
        $this->get(route('public.orders.create'))
            ->assertOk()
            ->assertSeeInOrder(['Kembali', 'Semak Progress'])
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="'.route('public.orders.progress').'"', false)
            ->assertSee('header-link nav-progress', false);
    }

    public function test_order_button_resumes_the_customers_unfinished_draft(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Draft Resume Customer',
            'customer_email' => 'draft@example.com',
            'customer_phone' => '0123456789',
        ]);

        $this->get(app(GenerateOrderAccessLinkService::class)->generate($order))
            ->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->get(route('public.orders.create'))
            ->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));
    }

    public function test_customer_can_start_an_order_and_reach_full_form(): void
    {
        $response = $this->post(route('public.orders.store'), [
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Pelanggan Baharu',
            'customer_email' => 'pelanggan@example.com',
            'customer_phone' => '0123456789',
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertSame('PUTERI_MANIS', $order->package_type);
        $response->assertRedirect();
        $response->assertRedirect('https://www.billplz-sandbox.com/bills/public-checkout');
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'amount' => '50.00',
        ]);
        $this->assertSame('Pelanggan Baharu', $order->customer_name);
        $this->assertCount(1, $order->packageSides);
    }

    public function test_failed_bill_creation_displays_payment_error_and_reuses_the_same_order(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'error' => ['type' => 'InvalidCollection'],
        ], 422)]);

        $input = [
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Cubaan Semula',
            'customer_email' => 'retry@example.com',
            'customer_phone' => '01222124456',
        ];

        $this->followingRedirects()
            ->post(route('public.orders.store'), $input)
            ->assertOk()
            ->assertSee('Pembayaran belum dapat dimulakan. Sila cuba lagi.');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('payment_events', [
            'event_type' => 'BILLPLZ_BILL_CREATION_FAILED',
        ]);

        $this->post(route('public.orders.store'), $input)
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_customer_can_create_folded_two_package_order_with_bride_side_first(): void
    {
        $response = $this->post(route('public.orders.store'), [
            'package_count' => 2,
            'package_type' => 'PUTERI_MANIS',
            'package_format' => 'FOLDED',
            'first_event_side' => 'PEREMPUAN',
            'customer_name' => 'Dua Majlis',
            'customer_email' => 'dua@example.com',
            'customer_phone' => '0122222222',
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect();
        $this->assertSame(2, $order->package_count);
        $this->assertSame('PUTERI_MANIS', $order->package_type);
        $this->assertSame('FOLDED', $order->package_format);
        $this->assertSame('PEREMPUAN', $order->first_event_side);
        $this->assertEqualsCanonicalizing(['LELAKI', 'PEREMPUAN'], $order->packageSides->pluck('side')->all());
        $this->assertSame('100.00', $order->payments()->firstOrFail()->amount);
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
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertSee($order->order_id)
            ->assertSee('50%')
            ->assertSee('Design Sedang Disediakan')
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
        $order->update(['status' => 'DESIGN_APPROVED']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('70%')
            ->assertSee('Bayaran baki selepas hasil design diluluskan')
            ->assertDontSee('Hasil Design Diluluskan')
            ->assertDontSee('Hasil design telah diluluskan secara dalaman.');
    }

    public function test_printed_order_shows_completed_printing_progress(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'PRINTED']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertSee('88%')
            ->assertSee('Cetakan Selesai')
            ->assertSee('Tempahan anda telah siap dan akan diteruskan ke proses pembungkusan.');
    }

    public function test_printing_order_shows_progress_button_without_granting_private_access(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => 'PRINTING']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('Lihat progress')
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
        $order->update(['status' => 'COMPLETED']);

        $secureLink = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($secureLink)
            ->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])
            ->assertOk()
            ->assertSee('Lihat progress')
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
        $order->update(['status' => 'DESIGN_READY']);

        $secureLink = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($secureLink)->assertRedirect(route('orders.dashboard', $order->order_id));

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertDontSee('Buka Semakan Penuh');
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
        $order->update(['status' => 'DESIGN_READY']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
            'customer_phone' => $order->customer_phone,
        ])
            ->assertOk()
            ->assertDontSee('Buka Semakan Penuh');

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
        $order->update(['status' => 'DESIGN_READY']);

        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])->assertOk();

        $this->get(route('orders.artwork.review', $order->order_id))->assertNotFound();
    }
}
