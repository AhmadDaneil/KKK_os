<?php

namespace Tests\Feature;

use App\Mail\GmailApiTransport;
use App\Models\Order;
use App\Services\Orders\CustomerOrderEmailNotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class GmailApiTransportTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer_reminders.enabled' => true,
            'mail.mailers.customer-reminders.transport' => 'gmail-api',
            'mail.mailers.customer-reminders.client_id' => 'test-client',
            'mail.mailers.customer-reminders.client_secret' => 'test-secret',
            'mail.mailers.customer-reminders.refresh_token' => 'test-refresh',
            'queue.default' => 'sync',
        ]);
    }

    public function test_order_status_email_is_sent_through_gmail_https_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-message-1']),
        ]);
        $order = Order::query()->create([
            'order_id' => 'KKK-261126-GMAIL',
            'package_count' => 1,
            'customer_email' => 'customer@example.test',
            'status' => 'DETAILS_INCOMPLETE',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }

            $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->hasHeader('Authorization', 'Bearer test-access')
                && str_contains($raw, 'To: customer@example.test')
                && str_contains($raw, 'kingkadkahwinhq@gmail.com');
        });
        $this->assertDatabaseHas('customer_email_notifications', ['order_id' => $order->id, 'status' => 'SENT']);
    }

    public function test_access_token_is_reused_until_it_expires(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-message']),
        ]);
        $transport = $this->transport();

        $transport->send($this->email());
        $transport->send($this->email());
        $this->travel(1)->hours();
        $transport->send($this->email());

        Http::assertSentCount(5);
    }

    public function test_unauthorized_send_refreshes_the_token_once(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'expired-access', 'expires_in' => 3600])
                ->push(['access_token' => 'new-access', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['id' => 'accepted-message']),
        ]);

        $sent = $this->transport()->send($this->email());

        $this->assertSame('accepted-message', $sent->getMessageId());
        Http::assertSentCount(4);
    }

    public function test_gmail_rejection_is_not_reported_as_sent(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['error' => 'Unavailable'], 503),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Gmail API did not accept the email (HTTP 503).');

        $this->transport()->send($this->email());
    }

    public function test_invalid_refresh_token_does_not_leak_google_response_secrets(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'description' => 'sensitive-token-value'], 400),
        ]);

        try {
            $this->transport()->send($this->email());
            $this->fail('Expected an OAuth refresh failure.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('Gmail OAuth token refresh failed (HTTP 400)', $exception->getMessage());
            $this->assertStringNotContainsString('sensitive-token-value', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_missing_gmail_message_id_is_treated_as_a_delivery_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response([]),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Gmail API did not accept the email (HTTP 200).');

        $this->transport()->send($this->email());
    }

    public function test_https_connection_failure_is_reported_as_a_transport_error(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::failedConnection()]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to reach Gmail API over HTTPS.');

        $this->transport()->send($this->email());
    }

    public function test_gmail_readiness_requires_oauth_credentials_instead_of_smtp_password(): void
    {
        $service = app(CustomerOrderEmailNotificationService::class);
        config(['mail.mailers.customer-reminders.password' => null]);
        $this->assertTrue($service->isReady());

        config(['mail.mailers.customer-reminders.refresh_token' => null]);

        $this->assertFalse($service->isReady());
    }

    private function transport(): GmailApiTransport
    {
        return Mail::mailer('customer-reminders')->getSymfonyTransport();
    }

    private function email(): Email
    {
        return (new Email)->from('kingkadkahwinhq@gmail.com')->to('customer@example.test')
            ->subject('Order progress')->text('Design approved.');
    }
}
