<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerEmailNotification;
use App\Mail\CustomerOrderNoticeMail;
use App\Models\CustomerEmailNotification;
use App\Models\Order;
use App\Models\OrderProgressUpdate;
use App\Models\User;
use App\Services\Orders\CreateOrderProgressUpdateService;
use App\Services\Orders\CustomerOrderEmailNotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CustomerProgressEmailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer_reminders.enabled' => true,
            'customer_reminders.mailer' => 'customer-reminders',
            'mail.mailers.customer-reminders.transport' => 'smtp',
            'mail.mailers.customer-reminders.username' => 'sender@example.test',
            'mail.mailers.customer-reminders.password' => 'test-only-password',
            'queue.default' => 'sync',
        ]);
    }

    #[DataProvider('progressStatuses')]
    public function test_a_progress_status_change_sends_a_customer_email(string $status): void
    {
        Mail::fake();
        $order = $this->order();

        $order->update(['status' => $status]);

        Mail::assertSent(CustomerOrderNoticeMail::class, fn (CustomerOrderNoticeMail $mail): bool => $mail->hasTo('customer@example.test')
            && $mail->notice['orderReference'] === $order->order_id);
        $this->assertDatabaseHas('customer_email_notifications', [
            'order_id' => $order->id,
            'status_to' => $status,
            'status' => CustomerEmailNotification::STATUS_SENT,
        ]);
    }

    public static function progressStatuses(): array
    {
        return array_combine($statuses = [
            'READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS', 'DESIGN_READY', 'DESIGN_APPROVED',
            'CORRECTION_REQUESTED', 'BALANCE_PENDING', 'PAID', 'READY_FOR_PRINT',
            'PRINTING', 'PRINTED', 'READY_FOR_PACKING', 'PACKING', 'PACKED',
            'READY_FOR_PICKUP', 'SHIPPED', 'COMPLETED',
        ], array_map(static fn (string $status): array => [$status], $statuses));
    }

    #[DataProvider('suppressedStatuses')]
    public function test_initial_and_internal_statuses_do_not_send_progress_email(string $status): void
    {
        Mail::fake();
        $order = $this->order();

        $order->update(['status' => $status]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('customer_email_notifications', 0);
    }

    public static function suppressedStatuses(): array
    {
        return [
            'booking pending' => ['BOOKING_PENDING'],
            'booked' => ['BOOKED'],
            'incomplete' => ['DETAILS_INCOMPLETE'],
            'details confirmed' => ['DETAILS_CONFIRMED'],
            'archived' => ['ARCHIVED'],
        ];
    }

    public function test_completed_milestones_are_delivered_even_after_the_order_moves_on(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $order = $this->order();

        foreach (['DESIGN_APPROVED', 'PAID', 'PRINTED', 'PACKED'] as $status) {
            $order->update(['status' => $status]);
        }

        $notices = CustomerEmailNotification::query()->orderBy('id')->get();
        $this->assertCount(4, $notices);
        foreach ($notices as $notice) {
            app()->call([new SendCustomerEmailNotification($notice->id), 'handle']);
        }

        Mail::assertSentCount(4);
        $this->assertSame(4, CustomerEmailNotification::query()->where('status', 'SENT')->count());
        $this->assertSame(0, CustomerEmailNotification::query()->where('status', 'SKIPPED')->count());
    }

    public function test_status_email_is_queued_only_after_the_transaction_commits(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $order = $this->order();

        DB::beginTransaction();
        $order->update(['status' => 'DESIGN_APPROVED']);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('customer_email_notifications', ['status_to' => 'DESIGN_APPROVED', 'status' => 'PENDING']);
        Mail::assertNothingSent();
    }

    public function test_rolled_back_status_changes_never_queue_email(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $order = $this->order();

        DB::beginTransaction();
        $order->update(['status' => 'DESIGN_APPROVED']);
        DB::rollBack();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('customer_email_notifications', 0);
        Mail::assertNothingSent();
    }

    public function test_staff_customer_visible_progress_sends_email_but_internal_notes_do_not(): void
    {
        Mail::fake();
        $order = $this->order();
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $service = app(CreateOrderProgressUpdateService::class);

        $service->create($order, $designer, 'Design anda telah siap.', null, true);
        $service->create($order, $designer, 'Internal production note.', null, false);

        Mail::assertSent(CustomerOrderNoticeMail::class, fn (CustomerOrderNoticeMail $mail): bool => $mail->notice['bodyText'] === 'Design anda telah siap.');
        $this->assertDatabaseCount('customer_email_notifications', 1);
    }

    public function test_withdrawn_progress_is_not_emailed_to_the_customer(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $order = $this->order();
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $update = app(CreateOrderProgressUpdateService::class)->create($order, $designer, 'Design siap.', null, true);
        $update->update(['visibility' => OrderProgressUpdate::VISIBILITY_INTERNAL]);
        $notice = CustomerEmailNotification::query()->sole();

        app()->call([new SendCustomerEmailNotification($notice->id), 'handle']);

        Mail::assertNothingSent();
        $this->assertSame('PROGRESS_NO_LONGER_VISIBLE', $notice->fresh()->error_code);
    }

    public function test_reprocessing_a_sent_status_notification_does_not_send_a_duplicate(): void
    {
        Mail::fake();
        $order = $this->order();
        $order->update(['status' => 'DESIGN_APPROVED']);
        $notice = CustomerEmailNotification::query()->sole();

        app()->call([new SendCustomerEmailNotification($notice->id), 'handle']);
        app(CustomerOrderEmailNotificationService::class)->queueStatusUpdate($order, 'DESIGN_APPROVED');

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('customer_email_notifications', 1);
    }

    public function test_delivery_failure_remains_recorded_and_can_be_retried(): void
    {
        config(['queue.default' => 'database']);
        $order = $this->order();
        $order->update(['status' => 'DESIGN_APPROVED']);
        $notice = CustomerEmailNotification::query()->sole();
        $job = new SendCustomerEmailNotification($notice->id);

        $job->failed(new RuntimeException('SMTP unavailable'));
        $this->assertSame(CustomerEmailNotification::STATUS_FAILED, $notice->fresh()->status);
        $retried = app(CustomerOrderEmailNotificationService::class)->queueStatusUpdate($order, 'DESIGN_APPROVED');

        $this->assertTrue($retried);
        $this->assertSame(CustomerEmailNotification::STATUS_PENDING, $notice->fresh()->status);
    }

    public function test_disabled_email_configuration_does_not_interrupt_order_updates(): void
    {
        Mail::fake();
        config(['customer_reminders.enabled' => false]);
        $order = $this->order();

        $order->update(['status' => 'DESIGN_APPROVED']);

        Mail::assertNothingSent();
        $this->assertSame('DESIGN_APPROVED', $order->fresh()->status);
        $this->assertDatabaseCount('customer_email_notifications', 0);
    }

    public function test_status_email_renders_successfully_through_the_array_mail_transport(): void
    {
        config(['mail.mailers.customer-reminders.transport' => 'array']);
        $order = $this->order();

        $order->update(['status' => 'DESIGN_APPROVED']);

        $this->assertDatabaseHas('customer_email_notifications', [
            'order_id' => $order->id,
            'status_to' => 'DESIGN_APPROVED',
            'status' => CustomerEmailNotification::STATUS_SENT,
        ]);
    }

    public function test_email_content_escapes_customer_supplied_progress_text(): void
    {
        $mail = new CustomerOrderNoticeMail('Progress tempahan', [
            'customerName' => 'Pelanggan',
            'orderReference' => 'KKK-261126-EMAIL',
            'title' => 'Kemas kini tempahan',
            'bodyText' => '<script>alert(1)</script>',
            'statusLabel' => null,
            'amount' => null,
            'actionUrl' => 'https://example.test/order',
            'actionLabel' => 'Buka Tempahan',
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_missing_customer_email_does_not_interrupt_order_updates(): void
    {
        Mail::fake();
        $order = $this->order();
        $order->update(['customer_email' => null]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('customer_email_notifications', 0);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'order_id' => 'KKK-261126-EMAIL',
            'package_count' => 1,
            'customer_name' => 'Email Customer',
            'customer_email' => 'customer@example.test',
            'status' => 'DETAILS_INCOMPLETE',
            'booking_payment_status' => 'PAID',
        ]);
    }
}
