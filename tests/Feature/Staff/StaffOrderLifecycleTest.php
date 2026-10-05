<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_management_can_cancel_an_order_with_a_required_reason(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->order('DETAILS_CONFIRMED');

        $this->actingAs($manager, 'staff')
            ->post(route('staff.orders.cancel', $order), ['reason' => 'Customer requested cancellation'])
            ->assertRedirect()
            ->assertSessionHas('status', "Tempahan {$order->order_id} telah dibatalkan dan rekod dikekalkan.");

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_CANCELLED',
            'from_status' => 'DETAILS_CONFIRMED',
            'to_status' => 'CANCELLED',
            'reason' => 'Customer requested cancellation',
            'actor_user_id' => $manager->id,
            'source' => 'STAFF',
        ]);
        $this->assertModelExists($order);
    }

    public function test_admin_can_archive_an_order_and_the_action_is_audited(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->order('COMPLETED');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.archive', $order), ['reason' => 'Completed order retention'])
            ->assertRedirect()
            ->assertSessionHas('status', "Tempahan {$order->order_id} telah diarkibkan dan rekod dikekalkan.");

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'ARCHIVED',
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_ARCHIVED',
            'from_status' => 'COMPLETED',
            'to_status' => 'ARCHIVED',
            'reason' => 'Completed order retention',
            'actor_user_id' => $admin->id,
            'source' => 'ADMIN',
        ]);
        $this->assertModelExists($order);
    }

    public function test_reason_is_required_before_an_order_can_be_cancelled(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->order('DETAILS_CONFIRMED');

        $this->actingAs($manager, 'staff')
            ->from(route('staff.orders.show', $order->order_id))
            ->post(route('staff.orders.cancel', $order), [])
            ->assertRedirect(route('staff.orders.show', $order->order_id))
            ->assertSessionHasErrors([
                'reason' => 'Sila nyatakan sebab tindakan ini.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'DETAILS_CONFIRMED',
        ]);
        $this->assertDatabaseMissing('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_CANCELLED',
        ]);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_unauthorized_staff_roles_cannot_cancel_orders(string $role): void
    {
        $staff = $this->staff($role);
        $order = $this->order('DETAILS_CONFIRMED');

        $this->actingAs($staff, 'staff')
            ->post(route('staff.orders.cancel', $order), ['reason' => 'Unauthorized request'])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'DETAILS_CONFIRMED',
        ]);
        $this->assertDatabaseMissing('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_CANCELLED',
        ]);
    }

    public function test_terminal_order_cannot_transition_to_the_other_terminal_status(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->order('CANCELLED');

        $this->actingAs($manager, 'staff')
            ->post(route('staff.orders.archive', $order), ['reason' => 'Invalid terminal transition'])
            ->assertRedirect()
            ->assertSessionHasErrors('order_lifecycle');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);
        $this->assertDatabaseMissing('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_ARCHIVED',
        ]);
    }

    public static function unauthorizedRoles(): array
    {
        return [
            'customer service' => [User::ROLE_CUSTOMER_SERVICE],
            'designer' => [User::ROLE_DESIGNER],
            'production' => [User::ROLE_PRODUCTION],
        ];
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function order(string $status): Order
    {
        return Order::create([
            'order_id' => 'KKK-261005-LIFE01',
            'status' => $status,
            'package_count' => 1,
            'customer_name' => 'Lifecycle Test Customer',
            'customer_email' => 'lifecycle@example.test',
            'customer_phone' => '0123456789',
            'booking_payment_status' => 'TEST',
        ]);
    }
}
