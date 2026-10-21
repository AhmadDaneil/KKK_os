<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWabotBookingConfirmation;
use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Payments\CreateBillplzBookingPaymentService;
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
            'conversation_id', 'business_unit', 'customer_name', 'phone', 'package_count',
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
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];

        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking_amount', 5000)
            ->assertJsonPath('payment_status', 'PENDING')
            ->assertJsonPath('provider_bill_id', 'booking-bill-1')
            ->assertJsonPath('reused_existing_booking', false)
            ->assertJsonPath('payment_url', 'https://www.billplz-sandbox.com/bills/booking-bill-1');
        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('draft_order_id', $first->json('draft_order_id'))
            ->assertJsonPath('provider_bill_id', 'booking-bill-1')
            ->assertJsonPath('reused_existing_booking', true)
            ->assertJsonPath('payment_url', $first->json('payment_url'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSentCount(1);

        $order = Order::query()->where('order_id', $first->json('draft_order_id'))->firstOrFail();
        $this->assertSame('60123456789', $order->customer_phone);
        $this->assertSame('PKG-A', $order->wabot_package_code);
        $this->assertNotEmpty($order->source_reference);
        $this->assertNotEmpty($order->wabot_active_booking_key);
        $this->assertSame('wabot-123456', $order->wabot_conversation_id);
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
        $paidOrder = $order->fresh();
        $this->assertSame('PAID', $paidOrder->booking_payment_status);
        $this->assertMatchesRegularExpression('/^KKK\d{6}-001$/', $paidOrder->customer_reference);
        $this->assertNotNull($paidOrder->dashboard_activated_at);
        $this->assertNull($paidOrder->wabot_active_booking_key);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('order_access_tokens', 1);
        $this->assertDatabaseCount('wabot_message_logs', 1);
        Queue::assertPushed(SendWabotBookingConfirmation::class, 1);
        Http::assertSentCount(1);
    }

    public function test_pending_booking_is_reused_even_if_wabot_repeats_the_request(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'booking-bill-2', 'collection_id' => 'sandbox-collection', 'amount' => 10000,
            'url' => 'https://www.billplz-sandbox.com/bills/booking-bill-2',
        ])]);
        $payload = [
            'conversation_id' => 'wabot-conflict', 'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789', 'business_unit' => 'DRS', 'package_count' => 2,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];
        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()->assertJsonPath('booking_amount', 10000);
        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('draft_order_id', $first->json('draft_order_id'))
            ->assertJsonPath('provider_bill_id', 'booking-bill-2')
            ->assertJsonPath('payment_url', $first->json('payment_url'))
            ->assertJsonPath('reused_existing_booking', true);
        $this->assertDatabaseCount('orders', 1);
        Http::assertSentCount(1);
    }

    public function test_same_conversation_can_create_a_new_booking_after_payment_is_paid(): void
    {
        $billNumber = 0;
        Http::fake(function () use (&$billNumber) {
            $billNumber++;

            return Http::response([
                'id' => 'booking-bill-'.$billNumber,
                'collection_id' => 'sandbox-collection',
                'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/booking-bill-'.$billNumber,
            ]);
        });

        $payload = [
            'conversation_id' => 'same-customer-conversation',
            'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789',
            'business_unit' => 'RYC',
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'PEREMPUAN',
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];

        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('payment_url', 'https://www.billplz-sandbox.com/bills/booking-bill-1');
        $this->postJson(route('api.billplz.callback'), $this->signedPayload([
            'id' => 'booking-bill-1', 'collection_id' => 'sandbox-collection',
            'paid' => 'true', 'state' => 'paid', 'amount' => '5000',
            'paid_amount' => '5000', 'paid_at' => '2026-10-20 12:00:00 +0800',
        ]))->assertOk();
        $second = $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('payment_url', 'https://www.billplz-sandbox.com/bills/booking-bill-2')
            ->assertJsonPath('reused_existing_booking', false);
        $this->assertNotSame($first->json('draft_order_id'), $second->json('draft_order_id'));

        $this->postJson(route('api.booking-intents.store'), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('draft_order_id', $second->json('draft_order_id'))
            ->assertJsonPath('payment_url', $second->json('payment_url'));
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertSame(2, Order::query()->where('wabot_conversation_id', 'same-customer-conversation')->count());
        Http::assertSentCount(2);
    }

    public function test_different_customers_receive_separate_bills(): void
    {
        $billNumber = 0;
        Http::fake(function () use (&$billNumber) {
            $billNumber++;

            return Http::response([
                'id' => 'customer-bill-'.$billNumber,
                'collection_id' => 'sandbox-collection',
                'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/customer-bill-'.$billNumber,
            ]);
        });
        $payload = [
            'conversation_id' => 'shared-reference',
            'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789',
            'business_unit' => 'RYC',
            'package_count' => 1,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];

        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertCreated();
        $second = $this->postJson(route('api.booking-intents.store'), [
            ...$payload,
            'customer_name' => 'Siti Aminah',
            'phone' => '0198765432',
        ], $headers)->assertCreated();

        $this->assertNotSame($first->json('draft_order_id'), $second->json('draft_order_id'));
        $this->assertNotSame($first->json('payment_url'), $second->json('payment_url'));
        $this->assertDatabaseCount('orders', 2);
        Http::assertSentCount(2);
    }

    public function test_cancelled_or_expired_booking_allows_a_new_bill_in_the_same_conversation(): void
    {
        $billNumber = 0;
        Http::fake(function () use (&$billNumber) {
            $billNumber++;

            return Http::response([
                'id' => 'lifecycle-bill-'.$billNumber,
                'collection_id' => 'sandbox-collection',
                'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/lifecycle-bill-'.$billNumber,
            ]);
        });
        $payload = [
            'conversation_id' => 'lifecycle-conversation',
            'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789',
            'business_unit' => 'RYC',
            'package_count' => 1,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];

        $first = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertCreated();
        $this->postJson(route('api.billplz.callback'), $this->signedPayload([
            'id' => 'lifecycle-bill-1', 'collection_id' => 'sandbox-collection',
            'paid' => 'false', 'state' => 'deleted', 'amount' => '5000',
            'paid_amount' => '0',
        ]))->assertOk();
        $second = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertCreated();
        $this->assertNotSame($first->json('draft_order_id'), $second->json('draft_order_id'));
        $secondOrder = Order::query()->where('order_id', $second->json('draft_order_id'))->firstOrFail();
        $secondOrder->payments()->where('payment_type', 'BOOKING_DEPOSIT')->update(['status' => 'EXPIRED']);
        $third = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertCreated();
        $this->assertNotSame($second->json('draft_order_id'), $third->json('draft_order_id'));
        $this->assertDatabaseCount('orders', 3);
        Http::assertSentCount(3);
    }

    public function test_same_customer_and_conversation_on_different_business_units_are_separate(): void
    {
        $billNumber = 0;
        Http::fake(function () use (&$billNumber) {
            $billNumber++;

            return Http::response([
                'id' => 'brand-bill-'.$billNumber,
                'collection_id' => 'sandbox-collection',
                'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/brand-bill-'.$billNumber,
            ]);
        });
        $payload = [
            'conversation_id' => 'same-conversation',
            'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789',
            'business_unit' => 'RYC',
            'package_count' => 1,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];

        $royalicious = $this->postJson(route('api.booking-intents.store'), $payload, $headers)->assertCreated();
        $kkk = $this->postJson(route('api.booking-intents.store'), [
            ...$payload,
            'business_unit' => 'KKK',
        ], $headers)->assertCreated();

        $this->assertNotSame($royalicious->json('draft_order_id'), $kkk->json('draft_order_id'));
        $this->assertDatabaseCount('orders', 2);
        Http::assertSentCount(2);
    }

    public function test_existing_pending_booking_from_previous_api_version_is_reused(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'legacy-bill', 'collection_id' => 'sandbox-collection', 'amount' => 5000,
            'url' => 'https://www.billplz-sandbox.com/bills/legacy-bill',
        ])]);
        $order = app(CreateOrderService::class)->create([
            'source' => 'WABOT',
            'source_reference' => 'old-conversation-id',
            'wabot_channel' => 'royalicious',
            'wabot_idempotency_key' => 'booking-old-conversation-id',
            'customer_name' => 'Nur Aisyah',
            'customer_phone' => '60123456789',
            'package_count' => 1,
        ]);
        app(CreateBillplzBookingPaymentService::class)->create($order);

        $this->postJson(route('api.booking-intents.store'), [
            'conversation_id' => 'old-conversation-id',
            'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789',
            'business_unit' => 'RYC',
            'package_count' => 1,
        ], ['X-KKK-API-Key' => 'inbound-test-key'])
            ->assertOk()
            ->assertJsonPath('draft_order_id', $order->order_id)
            ->assertJsonPath('payment_url', 'https://www.billplz-sandbox.com/bills/legacy-bill')
            ->assertJsonPath('reused_existing_booking', true);

        $this->assertSame('old-conversation-id', $order->fresh()->wabot_conversation_id);
        $this->assertDatabaseCount('orders', 1);
        Http::assertSentCount(1);
    }

    public function test_billplz_rejection_returns_502_and_retry_does_not_duplicate_the_order(): void
    {
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['error' => 'unavailable'], 503)]);
        $payload = [
            'conversation_id' => 'wabot-gateway-fail', 'customer_name' => 'Nur Aisyah',
            'phone' => '0123456789', 'business_unit' => 'CK', 'package_count' => 2,
        ];
        $headers = ['X-KKK-API-Key' => 'inbound-test-key'];
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
