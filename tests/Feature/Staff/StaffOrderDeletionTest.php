<?php

namespace Tests\Feature\Staff;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_incomplete_order(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->incompleteOrder();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.orders.destroy', $order->order_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_package_sides', ['order_id' => $order->id]);
    }

    public function test_operation_management_can_delete_a_two_package_incomplete_order(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder(2);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order->order_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_package_sides', ['order_id' => $order->id]);
    }

    public function test_completed_information_cannot_be_deleted(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();
        $order->update(['status' => 'DETAILS_CONFIRMED']);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order->order_id))
            ->assertRedirect()
            ->assertSessionHasErrors('order_delete');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_incomplete_order_with_payment_record_cannot_be_deleted(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();

        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'MANUAL_RECEIPT',
            'provider_reference' => 'DELETE-GUARD-TEST',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => 'PENDING',
        ]);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order->order_id))
            ->assertRedirect()
            ->assertSessionHasErrors('order_delete');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payment_transactions', ['id' => $payment->id]);
    }

    public function test_incomplete_order_with_failed_billplz_creation_can_be_deleted(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'amount' => 50.00,
            'currency' => 'MYR',
            'status' => 'FAILED',
            'metadata' => ['billplz' => ['checkout_status' => 'FAILED']],
        ]);
        $event = $payment->events()->create([
            'event_type' => 'BILLPLZ_BILL_CREATION_FAILED',
            'occurred_at' => now(),
        ]);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order->order_id))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('payment_transactions', ['id' => $payment->id]);
        $this->assertDatabaseMissing('payment_events', ['id' => $event->id]);
    }

    public function test_failed_billplz_payment_with_a_provider_reference_cannot_be_deleted(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'BILLPLZ',
            'provider_reference' => 'bill-with-possible-callback',
            'amount' => 50.00,
            'currency' => 'MYR',
            'status' => 'FAILED',
            'metadata' => ['billplz' => ['checkout_status' => 'FAILED']],
        ]);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('order_delete');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payment_transactions', ['id' => $payment->id]);
    }

    public function test_customer_service_designer_and_production_cannot_delete_orders(): void
    {
        foreach ([User::ROLE_CUSTOMER_SERVICE, User::ROLE_DESIGNER, User::ROLE_PRODUCTION] as $role) {
            $staff = $this->staff($role);
            $order = $this->incompleteOrder();

            $this->actingAs($staff)
                ->delete(route('staff.orders.destroy', $order->order_id))
                ->assertForbidden();

            $this->assertDatabaseHas('orders', ['id' => $order->id]);
        }
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function incompleteOrder(int $packageCount = 1)
    {
        return app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'customer_name' => 'Wrong Incomplete Order',
            'customer_email' => 'wrong@example.test',
            'customer_phone' => '0123456789',
        ]);
    }
}
