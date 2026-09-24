<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_incomplete_order_from_admin_orders(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->incompleteOrder();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee(route('admin.orders.destroy', $order), false)
            ->assertSee('Delete');

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_package_sides', ['order_id' => $order->id]);
    }

    public function test_operation_management_can_delete_an_incomplete_order(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_completed_information_cannot_be_deleted(): void
    {
        $manager = $this->staff(User::ROLE_OM);
        $order = $this->incompleteOrder();
        $order->update(['status' => 'DETAILS_CONFIRMED']);

        $this->actingAs($manager)
            ->delete(route('staff.orders.destroy', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('order_delete');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_specialist_staff_cannot_delete_orders(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $order = $this->incompleteOrder();

        $this->actingAs($designer)
            ->delete(route('staff.orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function incompleteOrder()
    {
        return app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Wrong Incomplete Order',
            'customer_email' => 'wrong@example.test',
            'customer_phone' => '0123456789',
        ]);
    }
}
