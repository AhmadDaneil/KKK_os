<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerEmailNotification;
use App\Models\CustomerEmailNotification;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendCustomerOrderRemindersTest extends TestCase
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
        ]);
    }

    #[DataProvider('detailsEligibility')]
    public function test_only_paid_incomplete_orders_due_after_24_hours_are_queued(int $hours, string $payment, bool $hasEmail, bool $expected): void
    {
        $this->freezeTime();
        $order = $this->order([
            'booking_payment_status' => $payment,
            'customer_email' => $hasEmail ? 'customer@example.test' : null,
            'dashboard_activated_at' => now()->subHours($hours),
        ]);
        Queue::fake([SendCustomerEmailNotification::class]);

        $this->artisan('customers:send-order-reminders')->assertSuccessful();

        $this->assertDatabaseCount('customer_email_notifications', $expected ? 1 : 0);
        Queue::assertPushed(SendCustomerEmailNotification::class, $expected ? 1 : 0);
        if ($expected) {
            $this->assertDatabaseHas('customer_email_notifications', [
                'order_id' => $order->id,
                'event_type' => CustomerEmailNotification::TYPE_DETAILS_INCOMPLETE,
                'status' => CustomerEmailNotification::STATUS_PENDING,
            ]);
        }
    }

    public static function detailsEligibility(): array
    {
        return [
            'before 24 hours' => [23, 'PAID', true, false],
            'at 24 hours' => [24, 'PAID', true, true],
            'unpaid order' => [25, 'PENDING', true, false],
            'missing email' => [25, 'PAID', false, false],
        ];
    }

    #[DataProvider('balanceEligibility')]
    public function test_only_overdue_unpaid_balances_are_queued(int $hours, string $status, int $balance, bool $expected): void
    {
        $this->freezeTime();
        $order = $this->order(['status' => $status]);
        $order->forceFill(['updated_at' => now()->subHours($hours)])->saveQuietly();
        $order->pricingSnapshot()->create([
            'subtotal' => 188,
            'total_amount' => 188,
            'booking_amount' => 50,
            'paid_amount' => 188 - $balance,
            'outstanding_amount' => $balance,
            'calculation_snapshot' => [],
        ]);
        Queue::fake([SendCustomerEmailNotification::class]);

        $this->artisan('customers:send-order-reminders')->assertSuccessful();

        $this->assertDatabaseCount('customer_email_notifications', $expected ? 1 : 0);
        Queue::assertPushed(SendCustomerEmailNotification::class, $expected ? 1 : 0);
        if ($expected) {
            $this->assertDatabaseHas('customer_email_notifications', [
                'order_id' => $order->id,
                'event_type' => CustomerEmailNotification::TYPE_BALANCE_DUE,
                'status' => CustomerEmailNotification::STATUS_PENDING,
            ]);
        }
    }

    public static function balanceEligibility(): array
    {
        return [
            'before 3 days' => [71, 'DESIGN_APPROVED', 138, false],
            'approved at 3 days' => [72, 'DESIGN_APPROVED', 138, true],
            'balance pending' => [73, 'BALANCE_PENDING', 138, true],
            'fully paid' => [73, 'DESIGN_APPROVED', 0, false],
            'printing already started' => [73, 'PRINTING', 138, false],
        ];
    }

    public function test_repeated_schedule_runs_do_not_duplicate_a_reminder(): void
    {
        $this->freezeTime();
        $this->order(['dashboard_activated_at' => now()->subHours(25)]);
        Queue::fake([SendCustomerEmailNotification::class]);

        $this->artisan('customers:send-order-reminders')->assertSuccessful();
        $this->artisan('customers:send-order-reminders')->assertSuccessful();

        $this->assertDatabaseCount('customer_email_notifications', 1);
        Queue::assertPushed(SendCustomerEmailNotification::class, 1);
    }

    public function test_disabled_reminders_do_not_queue_email(): void
    {
        config(['customer_reminders.enabled' => false]);
        $this->order(['dashboard_activated_at' => now()->subDays(2)]);
        Queue::fake([SendCustomerEmailNotification::class]);

        $this->artisan('customers:send-order-reminders')
            ->expectsOutput('Customer email reminders are disabled.')
            ->assertSuccessful();

        $this->assertDatabaseCount('customer_email_notifications', 0);
        Queue::assertNothingPushed();
    }

    public function test_missing_credentials_fail_without_queuing_email(): void
    {
        config(['mail.mailers.customer-reminders.refresh_token' => null]);
        Queue::fake([SendCustomerEmailNotification::class]);

        $this->artisan('customers:send-order-reminders')
            ->expectsOutput('Customer email reminders are enabled but the email sender is not configured.')
            ->assertFailed();

        $this->assertDatabaseCount('customer_email_notifications', 0);
        Queue::assertNothingPushed();
    }

    private function order(array $attributes): Order
    {
        return Order::withoutEvents(fn (): Order => Order::query()->create(array_merge([
            'order_id' => 'KKK-261127-REMINDER',
            'package_count' => 1,
            'customer_name' => 'Reminder Customer',
            'customer_email' => 'customer@example.test',
            'status' => 'DETAILS_INCOMPLETE',
            'booking_payment_status' => 'PAID',
        ], $attributes)));
    }
}
