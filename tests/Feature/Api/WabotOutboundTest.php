<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWabotBookingConfirmation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\WabotMessageLog;
use App\Services\Wabot\WabotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WabotOutboundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('services.wabot.base_url', 'https://app.wabot.my');
        config()->set('services.wabot.channels.kingkadkahwin', [
            'instance_id' => 'kkk-instance', 'access_token' => 'kkk-secret',
        ]);
    }

    public function test_booking_confirmation_sends_once_and_records_provider_response(): void
    {
        Http::fake(['app.wabot.my/*' => Http::response('queued', 200)]);
        $log = $this->paidMessageLog();

        app(SendWabotBookingConfirmation::class, ['messageLogId' => $log->id])->handle(app(WabotService::class));
        app(SendWabotBookingConfirmation::class, ['messageLogId' => $log->id])->handle(app(WabotService::class));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.wabot.my/api/send'
                && str_contains($request->header('Content-Type')[0] ?? '', 'application/json')
                && $request['number'] === '60123456789'
                && $request['type'] === 'text'
                && $request['instance_id'] === 'kkk-instance'
                && $request['access_token'] === 'kkk-secret'
                && str_contains($request['message'], 'KKK261020-001')
                && str_contains($request['message'], 'RM50.00')
                && str_contains($request['message'], '/access/test-token');
        });
        $this->assertDatabaseHas('wabot_message_logs', [
            'id' => $log->id, 'status' => 'SENT', 'attempts' => 1,
            'http_status' => 200, 'provider_response' => 'queued',
        ]);
    }

    public function test_authentication_failure_is_logged_without_retry(): void
    {
        Http::fake(['app.wabot.my/*' => Http::response('invalid token: kkk-secret', 401)]);
        $log = $this->paidMessageLog();

        app(SendWabotBookingConfirmation::class, ['messageLogId' => $log->id])->handle(app(WabotService::class));

        $this->assertDatabaseHas('wabot_message_logs', [
            'id' => $log->id, 'status' => 'FAILED', 'attempts' => 1,
            'http_status' => 401, 'provider_response' => 'invalid token: [redacted]',
        ]);
    }

    public function test_server_error_is_retryable(): void
    {
        Http::fake(['app.wabot.my/*' => Http::response('temporary error', 503)]);
        $log = $this->paidMessageLog();

        $this->expectException(RuntimeException::class);
        try {
            app(SendWabotBookingConfirmation::class, ['messageLogId' => $log->id])->handle(app(WabotService::class));
        } finally {
            $this->assertDatabaseHas('wabot_message_logs', [
                'id' => $log->id, 'status' => 'PENDING', 'attempts' => 1, 'http_status' => 503,
            ]);
        }
    }

    public function test_each_brand_uses_its_own_instance_on_the_shared_api_endpoint(): void
    {
        config()->set('services.wabot.channels.destinasi-raja-sehari', [
            'instance_id' => 'drs-instance',
            'access_token' => 'drs-secret',
        ]);
        Http::fake(['app.wabot.my/*' => Http::response('accepted', 200)]);

        $result = app(WabotService::class)->sendText('destinasi-raja-sehari', '60123456789', 'Test message');

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.wabot.my/api/send'
                && $request['instance_id'] === 'drs-instance'
                && $request['access_token'] === 'drs-secret';
        });
    }

    public function test_missing_brand_credentials_never_fall_back_to_another_sender(): void
    {
        config()->set('services.wabot.channels.destinasi-raja-sehari', [
            'instance_id' => null, 'access_token' => null,
        ]);
        config()->set('services.wabot.instance_id', 'shared-instance');
        config()->set('services.wabot.access_token', 'shared-secret');

        $result = app(WabotService::class)->sendText('destinasi-raja-sehari', '60123456789', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['retryable']);
        $this->assertSame('INVALID_CONFIGURATION_OR_PHONE', $result['error_code']);
        Http::assertNothingSent();
    }

    private function paidMessageLog(): WabotMessageLog
    {
        $order = Order::create([
            'order_id' => 'WABOT-DRAFT-1',
            'source' => 'WABOT',
            'wabot_channel' => 'kingkadkahwin',
            'customer_reference' => 'KKK261020-001',
            'customer_phone' => '60123456789',
            'package_count' => 1,
            'status' => 'ORDER_CREATED',
            'booking_payment_status' => 'PAID',
        ]);
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'provider_reference' => 'bill-'.$order->id,
            'amount' => '50.00',
            'currency' => 'MYR',
            'status' => 'PAID',
            'metadata' => ['customer_access' => [
                'dashboard_url_encrypted' => Crypt::encryptString('https://order.kingkadkahwin.com/access/test-token'),
            ]],
        ]);

        return WabotMessageLog::create([
            'order_id' => $order->id,
            'payment_transaction_id' => $payment->id,
            'phone' => $order->customer_phone,
            'message_type' => 'BOOKING_CONFIRMED',
            'status' => 'PENDING',
        ]);
    }
}
