<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RoyaliciousAutomationProbeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.wabot.royalicious_automation_probe_token', self::TOKEN);
    }

    public function test_returns_401_for_an_invalid_or_unconfigured_token(): void
    {
        $this->postJson($this->probeUrl(str_repeat('b', 64)), ['contact' => ['number' => '60123456789']])
            ->assertUnauthorized();

        config()->set('services.wabot.royalicious_automation_probe_token', '');

        $this->postJson($this->probeUrl(self::TOKEN), ['contact' => ['number' => '60123456789']])
            ->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_422_for_an_empty_payload_without_creating_a_booking(): void
    {
        $this->postJson($this->probeUrl(self::TOKEN), [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_PAYLOAD');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_413_for_a_payload_over_64_kilobytes(): void
    {
        $this->postJson($this->probeUrl(self::TOKEN), ['data' => str_repeat('x', 65536)])
            ->assertStatus(413)
            ->assertJsonPath('code', 'PAYLOAD_TOO_LARGE');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_records_only_field_paths_for_a_valid_json_payload_without_creating_a_booking(): void
    {
        Http::preventStrayRequests();
        Log::shouldReceive('info')->once()->withArgs(static function (string $message, array $context): bool {
            return $message === 'Royalicious automation probe received.'
                && $context['field_paths'] === [
                    'contact', 'contact.number', 'params', 'params.customer_name',
                    'params.package_count', 'params.package_code', 'params.payment_method',
                ]
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Nur Aisyah')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '60123456789');
        });

        $this->postJson($this->probeUrl(self::TOKEN), [
            'contact' => ['number' => '60123456789'],
            'params' => [
                'customer_name' => 'Nur Aisyah',
                'package_count' => '1',
                'package_code' => 'PUTERI_MANIS',
                'payment_method' => 'ONLINE_BANKING',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'observe_only');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_accepts_form_data_in_observe_only_mode(): void
    {
        Http::preventStrayRequests();
        Log::shouldReceive('info')->once();

        $this->post($this->probeUrl(self::TOKEN), [
            'contact' => ['number' => '60123456789'],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'observe_only');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    private function probeUrl(string $token): string
    {
        return route('api.wabot.royalicious.automation-probe', ['token' => $token]);
    }
}
