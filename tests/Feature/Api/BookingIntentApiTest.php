<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWabotBookingConfirmation;
use App\Models\Order;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingIntentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        config()->set('app.url', 'https://order.kingkadkahwin.com');
        config()->set('services.wabot.inbound_api_key', 'inbound-test-key');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
        config()->set('services.billplz.x_signature_key', 'sandbox-signature');
        Http::preventStrayRequests();
    }

    public function test_booking_intent_requires_separate_api_key_and_valid_payload(): void
    {
        $this->postJson(route('api.booking-intents.store'), [])->assertUnauthorized();
        $this->postJson(route('api.booking-intents.store'), [], [
            'X-KKK-API-Key' => 'inbound-test-key',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'conversation_id', 'business_unit', 'customer_name', 'phone', 'package_count', 'idempotency_key',
        ]);
        Http::assertNothingSent();
    }

    public function test_one_package_booking_is_idempotent_and_activates_only_after_verified_callback(): void
    {
        Queue::fake([SendWabotBookingConfirmation::class]);
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'booking-bill-1', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/booking-bill-1',
        ])]);
        $payload = [
            'conversation_id' => 'wabot-123456', 'customer_name' => 'Nur Aisyah',
            'phone' => '012-345 6789', 'email' => 'aisyah@example.test',
            'business_unit' => 'KKK', 'package_count' => 1,
            'package_code' => 'PKG-A', 'side' => 'PEREMPUAN',
        ];
        $headers = [
            'X-KKK-API-Key' => 'inbound-test-key',
            'Idempotency-Key' => 'booking-wabot-123456',
        ];

        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking_amount', 5000)
            ->assertJsonPath('payment_status', 'PENDING')
            ->assertJsonPath('payment_url', 'https://www.billplz-sandbox.com/bills/booking-bill-1');
        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertOk()->assertJsonPath('draft_order_id', $first->json('draft_order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSentCount(1);

        $order = Order::query()->where('order_id', $first->json('draft_order_id'))->firstOrFail();
        $this->assertSame('60123456789', $order->customer_phone);
        $this->assertSame('PKG-A', $order->wabot_package_code);
        $this->assertNull($order->customer_reference);
        $this->assertNull($order->dashboard_activated_at);
        $this->assertDatabaseCount('invoices', 0);

        $callback = $this->signedPayload([
            'id' => 'booking-bill-1', 'collection_id' => 'sandbox-collection',
            'paid' => 'true', 'state' => 'paid', 'amount' => '5000',
            'paid_amount' => '5000', 'paid_at' => '2026-10-20 12:00:00 +0800',
        ]);
        $this->postJson(route('api.billplz.callback'), $callback)->assertOk()->assertJsonPath('payment_status', 'PAID');
        $this->postJson(route('api.billplz.callback'), $callback)->assertOk();
        $paid = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertOk()->assertJsonPath('payment_status', 'PAID');
        $this->assertMatchesRegularExpression('/^KKK\d{6}-001$/', $paid->json('order_reference'));
        $this->assertNotEmpty($paid->json('dashboard_url'));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('order_access_tokens', 1);
        $this->assertDatabaseCount('wabot_message_logs', 1);
        Queue::assertPushed(SendWabotBookingConfirmation::class, 1);
        Http::assertSentCount(1);
    }

    public function test_conflicting_retry_is_rejected_without_creating_a_second_bill(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'booking-bill-2', 'collection_id' => 'sandbox-collection', 'amount' => 10000,
            'url' => 'https://www.billplz-sandbox.com/bills/booking-bill-2',
        ])]);
        $payload = [
            'conversation_id' => 'wabot-conflict', 'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789', 'business_unit' => 'DRS', 'package_count' => 2,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key', 'Idempotency-Key' => 'booking-wabot-conflict'];
        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()->assertJsonPath('booking_amount', 10000);
        $this->postJson(route('api.booking-intents.store'), [...$payload, 'package_count' => 1], $headers)
            ->assertStatus(409)->assertJsonPath('code', 'BOOKING_CONFLICT');
        $this->assertDatabaseCount('orders', 1);
        Http::assertSentCount(1);

        $this->postJson(route('api.billplz.callback'), $this->signedPayload([
            'id' => 'booking-bill-2', 'collection_id' => 'sandbox-collection',
            'paid' => 'true', 'state' => 'paid', 'amount' => '10000',
            'paid_amount' => '10000', 'paid_at' => '2026-10-20 12:00:00 +0800',
        ]))->assertOk();
        $paid = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertOk();
        $this->assertMatchesRegularExpression('/^DRS\d{6}-001$/', $paid->json('order_reference'));
    }

    public function test_billplz_rejection_returns_502_and_retry_does_not_duplicate_the_order(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['error' => 'unavailable'], 503)]);
        $payload = [
            'conversation_id' => 'wabot-gateway-fail', 'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789', 'business_unit' => 'CK', 'package_count' => 2,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key', 'Idempotency-Key' => 'booking-wabot-gateway-fail'];
        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertStatus(502)->assertJsonPath('code', 'BILLPLZ_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('payment_transactions', ['status' => 'FAILED']);
        Http::assertSentCount(1);
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
}
