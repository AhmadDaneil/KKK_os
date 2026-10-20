<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WabotOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        config()->set('app.url', 'https://167.71.196.30');
        config()->set('services.wabot.token', 'wabot-test-token');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
        config()->set('services.billplz.x_signature_key', 'sandbox-signature');
    }

    public function test_wabot_requires_valid_bearer_token(): void
    {
        $this->postJson(route('api.wabot.orders.store'), [])->assertUnauthorized();
        $this->postJson(route('api.wabot.orders.balance-payment.store', 'KKK-261019-0001'))
            ->assertUnauthorized();
    }

    public function test_wabot_cannot_start_balance_payment_before_artwork_approval(): void
    {
        $order = $this->paidWabotOrder('DESIGN_READY');
        Http::preventStrayRequests();

        $this->postJson(route('api.wabot.orders.balance-payment.store', $order->order_id), [], [
            'Authorization' => 'Bearer wabot-test-token',
        ])->assertUnprocessable()->assertJsonValidationErrors('billplz');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'BALANCE',
        ]);
    }

    public function test_wabot_receives_one_reusable_billplz_balance_link_after_artwork_approval(): void
    {
        $order = $this->paidWabotOrder('DESIGN_APPROVED');
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-balance', 'collection_id' => 'sandbox-collection', 'amount' => 25000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-balance',
        ])]);
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $url = route('api.wabot.orders.balance-payment.store', $order->order_id);

        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()
            ->assertJsonPath('balance.status', 'DUE')
            ->assertJsonPath('balance.amount', '250.00')
            ->assertJsonPath('balance.checkout_url', null);
        $this->postJson($url, [], $headers)
            ->assertOk()
            ->assertJsonPath('balance.status', 'PENDING')
            ->assertJsonPath('balance.checkout_url', 'https://www.billplz-sandbox.com/bills/wabot-balance');
        $this->postJson($url, [], $headers)
            ->assertOk()
            ->assertJsonPath('balance.checkout_url', 'https://www.billplz-sandbox.com/bills/wabot-balance');
        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()
            ->assertJsonPath('balance.checkout_url', 'https://www.billplz-sandbox.com/bills/wabot-balance');

        $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
            'id' => 'wabot-balance', 'collection_id' => 'sandbox-collection', 'paid' => 'true',
            'state' => 'paid', 'amount' => '25000', 'paid_amount' => '25000',
            'paid_at' => '2026-10-19 12:00:00 +0800',
        ]))->assertOk();
        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()
            ->assertJsonPath('balance.status', 'PAID')
            ->assertJsonPath('balance.checkout_url', null);

        Http::assertSentCount(1);
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'BALANCE',
            'provider' => 'BILLPLZ',
            'amount' => '250.00',
        ]);
    }

    public function test_wabot_creates_one_order_and_reuses_checkout_for_same_reference(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-bill-1', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-bill-1',
        ])]);
        $payload = [
            'external_reference' => 'conversation-123', 'package_count' => 1, 'side' => 'LELAKI',
            'package_type' => 'PUTERI_MANIS', 'customer_name' => 'Pelanggan Wabot',
            'customer_email' => 'wabot@example.test', 'customer_phone' => '012-345 6789',
        ];
        $headers = ['Authorization' => 'Bearer wabot-test-token'];

        $first = $this->postJson(route('api.wabot.orders.store'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('booking.amount', '50.00')
            ->assertJsonPath('booking.preferred_payment_option', 'DUITNOW_QR');
        $second = $this->postJson(route('api.wabot.orders.store'), $payload, $headers)->assertOk();

        $this->assertSame($first->json('order_id'), $second->json('order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('orders', [
            'source' => 'WABOT', 'source_reference' => 'conversation-123', 'customer_phone' => '60123456789',
        ]);
        Http::assertSentCount(1);
    }

    public function test_wabot_rejects_invalid_malaysian_phone_number(): void
    {
        $this->postJson(route('api.wabot.orders.store'), [
            'external_reference' => 'invalid-phone', 'package_count' => 1, 'side' => 'LELAKI',
            'package_type' => 'PUTERI_MANIS', 'customer_name' => 'Pelanggan Wabot',
            'customer_email' => 'wabot@example.test', 'customer_phone' => '123',
        ], ['Authorization' => 'Bearer wabot-test-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_phone');
    }

    public function test_wabot_needs_only_minimum_booking_details_and_keeps_dashboard_closed_until_paid(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-minimal', 'collection_id' => 'sandbox-collection', 'amount' => 10000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-minimal',
        ])]);
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $response = $this->postJson(route('api.wabot.orders.store'), [
            'external_reference' => 'conversation-minimal',
            'package_count' => 2,
            'package_type' => 'JELATA',
            'customer_name' => 'Booking Customer',
            'customer_phone' => '0123456789',
        ], $headers)->assertCreated()
            ->assertJsonPath('booking.amount', '100.00')
            ->assertJsonPath('booking.status', 'PENDING');
        Http::assertSent(static fn ($request): bool => $request->data()['mobile'] === '60123456789'
            && ! array_key_exists('email', $request->data()));

        $order = Order::query()->where('order_id', $response->json('order_id'))->firstOrFail();
        $this->assertNull($order->customer_reference);
        $this->assertNull($order->customer_email);
        $this->assertNull($order->dashboard_activated_at);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('order_access_tokens', 0);
        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()->assertJsonMissingPath('dashboard_url');
        $this->post(route('public.orders.progress.lookup'), [
            'order_id' => $order->order_id,
        ])->assertSessionHasErrors('order_id');
        $this->post(route('orders.access.store'), [
            'customer_reference' => $order->order_id,
            'customer_phone' => '0123456789',
        ])->assertSessionHasErrors('customer_reference');
        $this->expectException(HttpException::class);
        app(GenerateOrderAccessLinkService::class)->generate($order);
    }

    public function test_wabot_receives_reference_dashboard_link_and_receipt_only_after_verified_callback(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-paid', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-paid',
        ])]);
        $payload = [
            'external_reference' => 'conversation-paid', 'package_count' => 1, 'side' => 'LELAKI',
            'package_type' => 'PUTERI_MANIS', 'customer_name' => 'Paid Customer',
            'customer_phone' => '0123456789',
        ];
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $created = $this->postJson(route('api.wabot.orders.store'), $payload, $headers)->assertCreated();
        $orderId = $created->json('order_id');

        $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
            'id' => 'wabot-paid', 'collection_id' => 'sandbox-collection', 'paid' => 'true',
            'state' => 'paid', 'amount' => '5000', 'paid_amount' => '5000',
            'paid_at' => '2026-10-19 12:00:00 +0800',
        ]))->assertOk();

        $status = $this->getJson(route('api.wabot.orders.show', $orderId), $headers)
            ->assertOk()
            ->assertJsonPath('booking.status', 'PAID');
        $this->assertMatchesRegularExpression('/^KKK\d{6}-\d{3}$/', $status->json('customer_reference'));
        $this->assertSame($status->json('customer_reference'), $status->json('invoice_number'));
        $this->assertStringContainsString('/order/'.$orderId.'?', $status->json('dashboard_url'));
        $this->assertStringContainsString('/receipt?', $status->json('receipt_url'));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('order_access_tokens', 1);

        $this->postJson(route('api.wabot.orders.store'), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('booking.status', 'PAID')
            ->assertJsonPath('booking.checkout_url', null)
            ->assertJsonPath('customer_reference', $status->json('customer_reference'));
    }

    public function test_wabot_rejects_reuse_of_a_conversation_for_a_different_booking(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-conflict', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-conflict',
        ])]);
        $payload = [
            'external_reference' => 'conversation-conflict', 'package_count' => 1, 'side' => 'LELAKI',
            'package_type' => 'PUTERI_MANIS', 'customer_name' => 'First Customer',
            'customer_phone' => '0123456789',
        ];
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $this->postJson(route('api.wabot.orders.store'), $payload, $headers)->assertCreated();
        $this->postJson(route('api.wabot.orders.store'), [
            ...$payload, 'customer_phone' => '0198765432',
        ], $headers)->assertStatus(409)->assertJsonPath('code', 'BOOKING_CONFLICT');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_unpaid_billplz_callback_does_not_activate_the_dashboard(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'wabot-due', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/wabot-due',
        ])]);
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $created = $this->postJson(route('api.wabot.orders.store'), [
            'external_reference' => 'conversation-due', 'package_count' => 1, 'side' => 'PEREMPUAN',
            'package_type' => 'JELATA', 'customer_name' => 'Due Customer',
            'customer_phone' => '0123456789',
        ], $headers)->assertCreated();

        $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
            'id' => 'wabot-due', 'collection_id' => 'sandbox-collection', 'paid' => 'false',
            'state' => 'due', 'amount' => '5000', 'paid_amount' => '0',
        ]))->assertOk();

        $order = Order::query()->where('order_id', $created->json('order_id'))->firstOrFail();
        $this->assertSame('PENDING', $order->booking_payment_status);
        $this->assertNull($order->customer_reference);
        $this->assertNull($order->dashboard_activated_at);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('order_access_tokens', 0);
        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()->assertJsonMissingPath('dashboard_url');
    }

    public function test_late_success_on_an_older_bill_takes_priority_over_a_newer_pending_bill(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::sequence()
            ->push([
                'id' => 'wabot-old', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/wabot-old',
            ])
            ->push([
                'id' => 'wabot-new', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/wabot-new',
            ])]);
        $payload = [
            'external_reference' => 'conversation-late', 'package_count' => 1, 'side' => 'LELAKI',
            'package_type' => 'JELATA', 'customer_name' => 'Late Customer',
            'customer_phone' => '0123456789',
        ];
        $headers = ['Authorization' => 'Bearer wabot-test-token'];
        $created = $this->postJson(route('api.wabot.orders.store'), $payload, $headers)->assertCreated();
        $order = Order::query()->where('order_id', $created->json('order_id'))->firstOrFail();
        $order->payments()->firstOrFail()->update(['status' => 'FAILED']);
        $this->postJson(route('api.wabot.orders.store'), $payload, $headers)
            ->assertOk()->assertJsonPath('booking.status', 'PENDING');

        $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
            'id' => 'wabot-old', 'collection_id' => 'sandbox-collection', 'paid' => 'true',
            'state' => 'paid', 'amount' => '5000', 'paid_amount' => '5000',
            'paid_at' => '2026-10-19 12:00:00 +0800',
        ]))->assertOk();

        $this->getJson(route('api.wabot.orders.show', $order->order_id), $headers)
            ->assertOk()->assertJsonPath('booking.status', 'PAID')
            ->assertJsonPath('booking.checkout_url', null);
        $this->postJson(route('api.wabot.orders.store'), $payload, $headers)
            ->assertOk()->assertJsonPath('booking.status', 'PAID')
            ->assertJsonPath('booking.checkout_url', null);
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    private function signedPayload(array $payload): array
    {
        uksort($payload, static fn (string $left, string $right): int => strcasecmp($left, $right));
        $source = implode('|', array_map(
            static fn (string $key, mixed $value): string => $key.(string) $value,
            array_keys($payload), array_values($payload),
        ));
        $payload['x_signature'] = hash_hmac('sha256', $source, 'sandbox-signature');

        return $payload;
    }

    private function paidWabotOrder(string $status): Order
    {
        $order = app(CreateOrderService::class)->create([
            'source' => 'WABOT',
            'source_reference' => 'balance-'.$status,
            'package_count' => 1,
            'side' => 'LELAKI',
            'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Balance Customer',
            'customer_phone' => '0123456789',
        ]);
        $order->update(['status' => $status, 'booking_payment_status' => 'PAID']);
        $order->pricingSnapshot()->create([
            'currency' => 'MYR',
            'subtotal' => '300.00',
            'postage_amount' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => '300.00',
            'booking_amount' => '50.00',
            'paid_amount' => '50.00',
            'outstanding_amount' => '250.00',
            'calculation_snapshot' => [],
        ]);
        $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'amount' => '50.00',
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }
}
