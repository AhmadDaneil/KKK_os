<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoyaliciousProductionBookingWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const CUSTOMER_PHONE = '60198765432';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://order.kingkadkahwin.com');
        config()->set('services.wabot.royalicious_production_booking_webhook_token', self::TOKEN);
        config()->set('services.wabot.base_url', 'https://app.wabot.my');
        config()->set('services.wabot.channels.royalicious.instance_id', 'royalicious-production-test-instance');
        config()->set('services.wabot.channels.royalicious.access_token', 'royalicious-production-test-token');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'production-secret-for-http-fake');
        config()->set('services.billplz.base_url', 'https://www.billplz.com/api');
        config()->set('services.billplz.collection_id', 'production-collection-for-http-fake');
        config()->set('services.billplz.x_signature_key', 'production-signature-for-http-fake');
        config()->set('services.billplz.duitnow_qr_enabled', false);
        config()->set('services.wabot.royalicious_booking_test_phone', '60123456789');
    }

    public function test_creates_and_sends_a_live_checkout_link_to_a_customer_outside_the_sandbox_test_phone(): void
    {
        $this->useProductionEnvironment();
        $this->seedPackageCatalog();
        Http::preventStrayRequests();
        Http::fake($this->successfulBillAndMessageResponses());

        $response = $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment_status', 'PENDING')
            ->assertJsonPath('message_sent', true)
            ->assertJsonPath('reused_existing_booking', false);

        $order = Order::query()->where('order_id', $response->json('order_id'))->firstOrFail();
        $this->assertSame(self::CUSTOMER_PHONE, $order->customer_phone);
        $this->assertSame('Farhan Test', $order->customer_name);
        $this->assertSame('PUTERI_MANIS', $order->package_type);
        $this->assertSame('DETAILS_INCOMPLETE', $order->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.wabot.my/api/send'
            && $request['number'] === self::CUSTOMER_PHONE
            && str_contains($request['message'], 'https://www.billplz.com/bills/royalicious-production-bill'));
    }

    public function test_reuses_the_same_pending_bill_when_the_customer_retries(): void
    {
        $this->useProductionEnvironment();
        $this->seedPackageCatalog();
        Http::preventStrayRequests();
        Http::fake($this->successfulBillAndMessageResponses());

        $firstResponse = $this->postJson($this->webhookUrl(), $this->validPayload())->assertCreated();
        $retryResponse = $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reused_existing_booking', true);

        $this->assertSame($firstResponse->json('order_id'), $retryResponse->json('order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSentCount(3);
    }

    public function test_returns_503_in_a_non_production_environment_without_creating_a_bill(): void
    {
        Http::preventStrayRequests();

        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'PRODUCTION_ONLY');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_rejects_sandbox_billplz_configuration_in_production_without_creating_a_bill(): void
    {
        $this->useProductionEnvironment();
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        Http::preventStrayRequests();

        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'PRODUCTION_BILLPLZ_REQUIRED');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_returns_401_for_an_invalid_production_webhook_token(): void
    {
        Http::preventStrayRequests();

        $this->postJson(route('api.wabot.royalicious.production-booking', [
            'token' => str_repeat('c', 64),
        ]), $this->validPayload())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHORIZED');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_returns_422_for_an_invalid_customer_phone_without_creating_a_bill(): void
    {
        $this->useProductionEnvironment();
        Http::preventStrayRequests();
        $payload = $this->validPayload();
        $payload['contact']['number'] = '12345';

        $this->postJson($this->webhookUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_phone');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_resends_the_existing_bill_link_after_wabot_delivery_failure(): void
    {
        $this->useProductionEnvironment();
        $this->seedPackageCatalog();
        Http::preventStrayRequests();
        $messageAttempts = 0;
        Http::fake([
            'www.billplz.com/api/v3/bills' => Http::response([
                'id' => 'royalicious-production-bill',
                'collection_id' => 'production-collection-for-http-fake',
                'amount' => 5000,
                'url' => 'https://www.billplz.com/bills/royalicious-production-bill',
            ]),
            'https://app.wabot.my/api/send' => function () use (&$messageAttempts) {
                $messageAttempts++;

                return Http::response(['success' => $messageAttempts > 1], $messageAttempts > 1 ? 200 : 503);
            },
        ]);

        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertStatus(502)
            ->assertJsonPath('code', 'MESSAGE_DELIVERY_FAILED');
        $this->postJson($this->webhookUrl(), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reused_existing_booking', true);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSentCount(3);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'contact' => [
                'id' => self::CUSTOMER_PHONE.'@s.whatsapp.net',
                'number' => self::CUSTOMER_PHONE,
            ],
            'params' => [
                'customer_name' => 'Farhan Test',
                'package_count' => '1',
                'package_code' => 'PUTERI_MANIS',
                'payment_method' => 'ONLINE_BANKING',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function successfulBillAndMessageResponses(): array
    {
        return [
            'www.billplz.com/api/v3/bills' => Http::response([
                'id' => 'royalicious-production-bill',
                'collection_id' => 'production-collection-for-http-fake',
                'amount' => 5000,
                'url' => 'https://www.billplz.com/bills/royalicious-production-bill',
            ]),
            'https://app.wabot.my/api/send' => Http::response(['success' => true]),
        ];
    }

    private function webhookUrl(): string
    {
        return route('api.wabot.royalicious.production-booking', ['token' => self::TOKEN]);
    }

    private function useProductionEnvironment(): void
    {
        $this->app['env'] = 'production';
    }

    private function seedPackageCatalog(): void
    {
        $this->app->make(KkkPackagePriceCatalogSeeder::class)->run();
    }
}
