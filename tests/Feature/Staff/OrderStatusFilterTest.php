<?php

namespace Tests\Feature\Staff;

use App\Models\FulfilmentJob;
use App\Models\Order;
use App\Models\OrderFulfilment;
use App\Models\PackingJob;
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

    public function test_om_can_open_every_department_queue_for_monitoring(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_OM,
            'is_active' => true,
        ]);

        foreach ([
            'design' => 'Design Queue',
            'printing' => 'Production Queue',
            'packing' => 'Packing Queue',
            'fulfilment' => 'Fulfilment Queue',
        ] as $workstream => $heading) {
            $this->actingAs($manager, 'staff')
                ->get(route('staff.orders.index', ['workstream' => $workstream]))
                ->assertOk()
                ->assertSee($heading);
        }
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

    public function test_admin_packing_queue_only_lists_orders_with_active_packing_work(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $activeOrder = $this->order('KKK-PACKING-ACTIVE', 'PACKING', 'Active Packing Customer');
        $packedOrder = $this->order('KKK-PACKING-DONE', 'PACKED', 'Packed Customer');

        PackingJob::query()->create([
            'order_id' => $activeOrder->id,
            'status' => 'READY_FOR_PACKING',
        ]);
        PackingJob::query()->create([
            'order_id' => $packedOrder->id,
            'status' => 'PACKED',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', ['workstream' => 'packing']))
            ->assertOk()
            ->assertSee('Packing Queue')
            ->assertSee($activeOrder->order_id)
            ->assertDontSee($packedOrder->order_id);
    }

    public function test_admin_fulfilment_queue_only_lists_orders_with_active_fulfilment_work(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $activeOrder = $this->order('KKK-FULFIL-ACTIVE', 'PACKED', 'Active Fulfilment Customer');
        $collectedOrder = $this->order('KKK-FULFIL-DONE', 'PACKED', 'Collected Customer');

        $this->fulfilmentJob($activeOrder, 'READY');
        $this->fulfilmentJob($collectedOrder, 'COLLECTED');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', ['workstream' => 'fulfilment']))
            ->assertOk()
            ->assertSee('Fulfilment Queue')
            ->assertSee($activeOrder->order_id)
            ->assertDontSee($collectedOrder->order_id);
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

    private function fulfilmentJob(Order $order, string $status): FulfilmentJob
    {
        $fulfilment = OrderFulfilment::query()->create([
            'order_id' => $order->id,
            'method' => 'PICKUP',
        ]);
        $packingJob = PackingJob::query()->create([
            'order_id' => $order->id,
            'status' => 'PACKED',
        ]);

        return FulfilmentJob::query()->create([
            'order_id' => $order->id,
            'order_fulfilment_id' => $fulfilment->id,
            'packing_job_id' => $packingJob->id,
            'method' => 'PICKUP',
            'status' => $status,
        ]);
    }
}
