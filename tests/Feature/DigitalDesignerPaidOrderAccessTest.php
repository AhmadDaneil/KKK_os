<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DigitalDesignerPaidOrderAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_digital_designer_can_read_a_paid_order_without_a_physical_design_assignment(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $order = $this->order('KKK-261126-0008');

        $this->actingAs($designer)->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Digital paid customer')
            ->assertSee('These order details are read-only.')
            ->assertDontSee('Start Design');
    }

    public function test_digital_designer_sees_paid_incomplete_orders_in_the_order_list(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $paid = $this->order('KKK-261126-0008');
        $unpaid = $this->order('KKK-261126-0007', ['booking_payment_status' => 'PENDING', 'customer_name' => 'Unpaid customer']);

        $this->actingAs($designer)->get(route('staff.orders.index'))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->contains('id', $paid->id) && ! $orders->contains('id', $unpaid->id));
    }

    public function test_queue_view_order_link_uses_internal_id_instead_of_customer_reference(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $paid = $this->order('KKK-261126-0008', ['customer_reference' => 'KKK261126-007']);
        $unpaid = $this->order('KKK-261126-0007', ['booking_payment_status' => 'PENDING']);

        $this->actingAs($designer)->get(route('staff.digital-designer.orders.index'))
            ->assertOk()
            ->assertSee('View Order')
            ->assertSee(route('staff.orders.show', $paid->order_id), false)
            ->assertDontSee(route('staff.orders.show', $unpaid->order_id), false);
    }

    #[DataProvider('inaccessibleOrders')]
    public function test_digital_designer_cannot_open_unpaid_or_closed_unassigned_orders(string $paymentStatus, string $status): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $order = $this->order('KKK-261126-0007', ['booking_payment_status' => $paymentStatus, 'status' => $status]);

        $this->actingAs($designer)->get(route('staff.orders.show', $order->order_id))->assertNotFound();
    }

    public static function inaccessibleOrders(): array
    {
        return [
            'unpaid draft' => ['PENDING', 'DETAILS_INCOMPLETE'],
            'unpaid operational order' => ['PENDING', 'READY_FOR_DESIGN'],
            'cancelled paid order' => ['PAID', 'CANCELLED'],
            'archived paid order' => ['PAID', 'ARCHIVED'],
        ];
    }

    public function test_search_cannot_bypass_the_paid_order_filter(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $this->order('KKK-261126-0008');
        $this->order('KKK-261126-0007', ['booking_payment_status' => 'PENDING', 'customer_name' => 'Hidden unpaid customer']);

        $this->actingAs($designer)->get(route('staff.orders.index', ['search' => 'Hidden unpaid customer']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->isEmpty());
    }

    public function test_physical_designer_still_needs_an_order_assignment(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $order = $this->order('KKK-261126-0008', ['status' => 'DESIGN_APPROVED']);

        $this->actingAs($designer)->get(route('staff.orders.show', $order->order_id))->assertNotFound();
    }

    public function test_paid_order_read_access_does_not_grant_unassigned_progress_writes(): void
    {
        $designer = User::factory()->create(['role' => User::ROLE_DIGITAL_DESIGNER, 'is_active' => true]);
        $order = $this->order('KKK-261126-0008');

        $this->actingAs($designer)->post(route('staff.orders.progress-updates.store', $order), [
            'message' => 'Unassigned change',
            'customer_visible' => true,
        ])->assertNotFound();

        $this->assertDatabaseCount('order_progress_updates', 0);
    }

    public function test_guest_cannot_open_a_paid_order(): void
    {
        $order = $this->order('KKK-261126-0008');

        $this->get(route('staff.orders.show', $order->order_id))->assertRedirect(route('staff.login'));
    }

    /** @param array<string, mixed> $attributes */
    private function order(string $orderId, array $attributes = []): Order
    {
        return Order::query()->create([
            'order_id' => $orderId,
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Digital paid customer',
            'booking_payment_status' => 'PAID',
            'status' => 'DETAILS_INCOMPLETE',
            ...$attributes,
        ]);
    }
}
