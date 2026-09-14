<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderLifecycleTerminalStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_order_can_be_cancelled_without_deletion_and_is_audited(): void
    {
        $order = $this->makeOrder('KKK-260914-T101', 1);

        $result = app(OrderLifecycleService::class)->cancel(
            $order,
            'Customer requested cancellation',
            null,
            'TEST',
        );

        $this->assertSame('CANCELLED', $result->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_id' => 'KKK-260914-T101',
            'package_count' => 1,
            'customer_name' => 'T12 One Package',
            'status' => 'CANCELLED',
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_CANCELLED',
            'from_status' => 'DETAILS_INCOMPLETE',
            'to_status' => 'CANCELLED',
            'reason' => 'Customer requested cancellation',
            'source' => 'TEST',
        ]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_two_package_order_can_be_archived_without_deletion_and_is_audited(): void
    {
        $order = $this->makeOrder('KKK-260914-T102', 2);

        $result = app(OrderLifecycleService::class)->archive(
            $order,
            'Operational archive',
            null,
            'TEST',
        );

        $this->assertSame('ARCHIVED', $result->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_id' => 'KKK-260914-T102',
            'package_count' => 2,
            'customer_name' => 'T12 Two Package',
            'status' => 'ARCHIVED',
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_ARCHIVED',
            'from_status' => 'DETAILS_INCOMPLETE',
            'to_status' => 'ARCHIVED',
            'reason' => 'Operational archive',
            'source' => 'TEST',
        ]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cancel_is_idempotent_and_does_not_duplicate_audit_event(): void
    {
        $order = $this->makeOrder('KKK-260914-T103', 1);
        $service = app(OrderLifecycleService::class);

        $service->cancel($order, 'First cancellation', null, 'TEST');
        $service->cancel($order->fresh(), 'Second request', null, 'TEST');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(
            1,
            $order->statusEvents()->where('event_type', 'ORDER_CANCELLED')->count()
        );
    }

    public function test_cancelled_order_cannot_be_reactivated_by_direct_status_update(): void
    {
        $order = $this->makeOrder('KKK-260914-T104', 1);
        app(OrderLifecycleService::class)->cancel($order, null, null, 'TEST');

        $this->expectException(RuntimeException::class);

        $order->fresh()->update([
            'status' => 'PAID',
        ]);
    }

    public function test_archived_order_cannot_be_reactivated_by_direct_status_update(): void
    {
        $order = $this->makeOrder('KKK-260914-T105', 2);
        app(OrderLifecycleService::class)->archive($order, null, null, 'TEST');

        $this->expectException(RuntimeException::class);

        $order->fresh()->update([
            'status' => 'COMPLETED',
        ]);
    }

    public function test_terminal_statuses_cannot_transition_between_each_other(): void
    {
        $order = $this->makeOrder('KKK-260914-T106', 1);
        $service = app(OrderLifecycleService::class);

        $service->cancel($order, null, null, 'TEST');

        $this->expectException(RuntimeException::class);

        $service->archive($order->fresh(), null, null, 'TEST');
    }

    private function makeOrder(string $orderId, int $packageCount): Order
    {
        return Order::create([
            'order_id' => $orderId,
            'package_count' => $packageCount,
            'customer_name' => $packageCount === 1 ? 'T12 One Package' : 'T12 Two Package',
            'customer_email' => 't12@example.test',
            'customer_phone' => '0123456789',
            'booking_payment_status' => 'TEST',
            'status' => 'DETAILS_INCOMPLETE',
        ]);
    }
}
