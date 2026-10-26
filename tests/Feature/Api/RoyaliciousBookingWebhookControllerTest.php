<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoyaliciousBookingWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const TEST_PHONE = '60123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        config()->set('app.url', 'https://order.kingkadkahwin.com');
        config()->set('services.wabot.royalicious_booking_webhook_token', self::TOKEN);
        config()->set('services.wabot.royalicious_booking_test_phone', self::TEST_PHONE);
        config()->set('services.wabot.base_url', 'https://app.wabot.my');
        config()->set('services.wabot.channels.royalicious.instance_id', 'royalicious-test-instance');
        config()->set('services.wabot.channels.royalicious.access_token', 'royalicious-test-token');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
        config()->set('services.billplz.x_signature_key', 'sandbox-signature');
        Http::preventStrayRequests();
    }

    public function test_creates_one_sandbox_booking_and_sends_the_link_only_to_the_test_phone(): void
    {
        Http::fake([
            'www.billplz-sandbox.com/*' => Http::response([
                'id' => 'royalicious-sandbox-bill',
                'collection_id' => 'sandbox-collection',
                'amount' => 5000,
                'url' => 'https://www.billplz-sandbox.com/bills/royalicious-sandbox-bill',
            ]),
            'https://app.wabot.my/api/send' => Http::response(['success' => true]),
        ]);

        $first = $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment_status', 'PENDING')
            ->assertJsonPath('message_sent', true)
            ->assertJsonPath('reused_existing_booking', false);
        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reused_existing_booking', true);

        $order = Order::query()->where('order_id', $first->json('order_id'))->firstOrFail();
        $this->assertSame('60123456789', $order->customer_phone);
        $this->assertSame('Rahman', $order->customer_name);
        $this->assertSame('PUTERI_MANIS', $order->package_type);
        $this->assertSame('PUTERI_MANIS', $order->wabot_package_code);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSentCount(3);
        Http::assertSent(static fn ($request): bool => $request->url() === 'https://app.wabot.my/api/send'
            && $request['number'] === self::TEST_PHONE
            && str_contains($request['message'], 'https://www.billplz-sandbox.com/bills/royalicious-sandbox-bill')
            && str_contains($request['message'], 'Jangan buat bayaran.'));
    }

    public function test_rejects_a_non_sandbox_billplz_configuration_without_creating_a_booking(): void
    {
        config()->set('services.billplz.base_url', 'https://www.billplz.com/api');

        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertStatus(503)
            ->assertJsonPath('code', 'SANDBOX_ONLY');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_rejects_a_phone_outside_the_test_allowlist_without_creating_a_booking(): void
    {
        $payload = $this->validPayload();
        $payload['contact']['number'] = '60198765432';

        $this->postJson($this->webhookUrl(), $payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'TEST_PHONE_ONLY');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_rejects_missing_booking_inputs_without_creating_a_booking(): void
    {
        $payload = $this->validPayload();
        unset($payload['params']['customer_name']);

        $this->postJson($this->webhookUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('params.customer_name');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_rejects_an_invalid_token_without_creating_a_booking(): void
    {
        $this->postJson(route('api.wabot.royalicious.booking', ['token' => str_repeat('b', 64)]), $this->validPayload())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHORIZED');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    /** @return array{contact: array{number: string}, params: array{customer_name: string, package_count: string, package_code: string, payment_method: string}} */
    private function validPayload(): array
    {
        return [
            'contact' => ['number' => '012-345-6789'],
            'params' => [
                'customer_name' => 'Rahman',
                'package_count' => '1',
                'package_code' => 'PUTERI_MANIS',
                'payment_method' => 'ONLINE_BANKING',
            ],
        ];
    }

    private function webhookUrl(): string
    {
        return route('api.wabot.royalicious.booking', ['token' => self::TOKEN]);
    }
}
