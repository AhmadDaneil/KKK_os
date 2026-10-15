<?php

namespace Tests\Feature\Api;

use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
    }

    public function test_wabot_requires_valid_bearer_token(): void
    {
        $this->postJson(route('api.wabot.orders.store'), [])->assertUnauthorized();
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
}
