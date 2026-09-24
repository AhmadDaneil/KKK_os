<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_completed_filter_is_available_and_excludes_completed_orders(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_OM,
            'is_active' => true,
        ]);

        $activeOrder = $this->order('KKK-FILTER-ACTIVE', 'DETAILS_CONFIRMED', 'Active Customer');
        $completedOrder = $this->order('KKK-FILTER-DONE', 'COMPLETED', 'Completed Customer');

        $this->actingAs($manager, 'staff')
            ->get(route('staff.orders.index', [
                'status' => Order::STATUS_FILTER_NOT_COMPLETED,
            ]))
            ->assertOk()
            ->assertSee('value="NOT_COMPLETED" selected', false)
            ->assertSee($activeOrder->order_id)
            ->assertDontSee($completedOrder->order_id);
    }

    public function test_admin_portal_uses_the_same_not_completed_filter(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $activeOrder = $this->order('KKK-ADMIN-ACTIVE', 'PRINTING', 'Admin Active Customer');
        $completedOrder = $this->order('KKK-ADMIN-DONE', 'COMPLETED', 'Admin Completed Customer');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', [
                'status' => Order::STATUS_FILTER_NOT_COMPLETED,
            ]))
            ->assertOk()
            ->assertSee($activeOrder->order_id)
            ->assertDontSee($completedOrder->order_id);
    }

    private function order(string $orderId, string $status, string $customerName): Order
    {
        return Order::query()->create([
            'order_id' => $orderId,
            'package_count' => 1,
            'customer_name' => $customerName,
            'status' => $status,
        ]);
    }
}
