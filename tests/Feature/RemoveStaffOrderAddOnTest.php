<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\AddStaffOrderAddOnService;
use App\Services\Orders\CreateOrderService;
use App\Services\Packing\BuildPackingChecklistService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemoveStaffOrderAddOnTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_one_board_line_recalculates_invoice_and_retains_other_items_and_payments(): void
    {
        [$order, $cs] = $this->order();
        $requestId = (string) Str::uuid();
        $removed = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'WELCOME_BOARD_MIRROR_GOLD_ROSEGOLD_SILVER', 10, 'Gold', $requestId);
        $retained = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'WELCOME_BOARD_MIRROR_GOLD_ROSEGOLD_SILVER', 1, null, (string) Str::uuid());
        $invoice = $order->invoice()->firstOrFail();
        $number = $invoice->invoice_number;
        $this->actingAs($cs, 'staff')->get(route('staff.orders.show', $order->order_id))->assertSee('Remove Add-On');

        $this->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $removed]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertModelMissing($removed);
        $this->assertModelExists($retained);
        $this->assertSame('302.00', $order->pricingSnapshot()->value('total_amount'));
        $this->assertSame('114.00', $order->pricingSnapshot()->first()->calculation_snapshot['add_on_amount']);
        $invoice->refresh();
        $this->assertSame($number, $invoice->invoice_number);
        $this->assertSame('50.00', $invoice->amount_paid);
        $this->assertSame('252.00', $invoice->balance_due);
        $this->assertSame('READY_FOR_DESIGN', $order->fresh()->status);
        $event = $order->statusEvents()->where('event_type', 'ADDON_REMOVED')->sole();
        $this->assertSame($cs->id, $event->actor_user_id);
        $this->assertSame(10, $event->metadata['quantity']);
        $this->assertSame('1140.00', $event->metadata['amount']);
        $this->assertSame('15.00', $event->metadata['item_metadata']['welcome_board_postage_per_unit']);
        $this->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $removed->id]))->assertNotFound();
        $this->assertSame('302.00', $order->pricingSnapshot()->value('total_amount'));
        $this->post(route('staff.orders.add-ons.store', $order), ['code' => 'WELCOME_BOARD_MIRROR_GOLD_ROSEGOLD_SILVER', 'quantity' => 10, 'request_id' => $requestId])
            ->assertSessionHasErrors('add_on');
        $this->assertSame(1, $order->items()->where('item_type', 'ADDON')->count());
    }

    public function test_removing_last_addon_restores_exact_total_and_packing_list(): void
    {
        [$order, $cs] = $this->order();
        $item = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'WEDDING_BOX_READY_MADE', 3, null, (string) Str::uuid());

        $this->actingAs($cs, 'staff')->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $item]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = $order->pricingSnapshot()->first();
        $this->assertSame('188.00', $snapshot->total_amount);
        $this->assertSame('138.00', $snapshot->outstanding_amount);
        $this->assertSame('0.00', $snapshot->calculation_snapshot['add_on_amount']);
        $this->assertSame([], $snapshot->calculation_snapshot['add_ons']);
        $this->assertNotContains('Wedding Box Ready Made', array_column(app(BuildPackingChecklistService::class)->build($order->fresh()), 'description'));
    }

    public function test_order_scope_and_package_items_cannot_be_removed(): void
    {
        [$order, $cs] = $this->order();
        [$other] = $this->order();
        $foreign = app(AddStaffOrderAddOnService::class)->add($other, $cs, 'BANTING_2X4', 1, null, (string) Str::uuid());
        $package = $order->items()->create(['item_type' => 'PACKAGE', 'item_code' => 'PACKAGE', 'description' => 'Package', 'quantity' => 1, 'unit_price' => '188.00', 'line_total' => '188.00']);
        $this->actingAs($cs, 'staff');

        $this->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $foreign]))->assertNotFound();
        $this->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $package]))->assertNotFound();

        $this->assertModelExists($foreign);
        $this->assertModelExists($package);
        $this->assertSame('188.00', $order->pricingSnapshot()->value('total_amount'));
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_staff_cannot_remove_addons(string $role): void
    {
        [$order, $cs] = $this->order();
        $item = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'BANTING_2X4', 1, null, (string) Str::uuid());
        $staff = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($staff, 'staff')->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $item]))->assertForbidden();

        $this->assertModelExists($item);
        $this->assertSame('207.00', $order->pricingSnapshot()->value('total_amount'));
    }

    public static function unauthorizedRoles(): array
    {
        return [['DESIGNER'], ['DIGITAL_DESIGNER'], ['PRODUCTION']];
    }

    public function test_paid_items_requiring_a_refund_are_not_silently_removed(): void
    {
        [$order, $cs] = $this->order();
        $item = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'BANTING_2X4', 1, null, (string) Str::uuid());
        $order->payments()->create(['payment_type' => 'BALANCE', 'provider' => 'TEST', 'amount' => '157.00', 'currency' => 'MYR', 'status' => 'PAID']);
        app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        $this->actingAs($cs, 'staff')->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $item]))->assertSessionHasErrors('add_on');

        $this->assertModelExists($item);
        $this->assertSame('207.00', $order->invoice()->value('amount_paid'));
        $this->assertSame('207.00', $order->invoice()->value('total_amount'));
        $this->assertDatabaseMissing('order_status_events', ['order_id' => $order->id, 'event_type' => 'ADDON_REMOVED']);
    }

    #[DataProvider('lockedStates')]
    public function test_removal_obeys_existing_packing_and_payment_locks(string $state): void
    {
        [$order, $cs] = $this->order();
        $item = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'BANTING_2X4', 1, null, (string) Str::uuid());
        if ($state === 'PENDING_PAYMENT') {
            $order->payments()->create(['payment_type' => 'BALANCE', 'provider' => 'TEST', 'amount' => '157.00', 'currency' => 'MYR', 'status' => 'PENDING']);
        } else {
            $order->update(['status' => $state]);
        }

        $this->actingAs($cs, 'staff')->delete(route('staff.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $item]))->assertSessionHasErrors('add_on');

        $this->assertModelExists($item);
        $this->assertSame('207.00', $order->pricingSnapshot()->value('total_amount'));
    }

    public static function lockedStates(): array
    {
        return [['PENDING_PAYMENT'], ['PACKING'], ['COMPLETED']];
    }

    #[DataProvider('managementPortals')]
    public function test_management_can_remove_addons(string $portal, string $role): void
    {
        [$order, $cs] = $this->order();
        $item = app(AddStaffOrderAddOnService::class)->add($order, $cs, 'BANTING_2X4', 1, null, (string) Str::uuid());
        $manager = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($manager, $portal)->delete(route($portal.'.orders.add-ons.destroy', ['order' => $order, 'orderItem' => $item]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertModelMissing($item);
        $this->assertSame($manager->id, $order->statusEvents()->where('event_type', 'ADDON_REMOVED')->value('actor_user_id'));
    }

    public static function managementPortals(): array
    {
        return [['admin', User::ROLE_ADMIN], ['staff', User::ROLE_OM]];
    }

    /** @return array{Order, User} */
    private function order(): array
    {
        $cs = User::factory()->create(['role' => User::ROLE_CUSTOMER_SERVICE, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'package_type' => 'PUTERI_MANIS', 'customer_name' => 'Remove Add-On Customer']);
        $order->packageSides()->update(['card_quantity' => 200]);
        $order->forceFill(['status' => 'READY_FOR_DESIGN', 'booking_payment_status' => 'PAID', 'card_quantity' => 200])->save();
        $order->pricingSnapshot()->create(['currency' => 'MYR', 'subtotal' => '188.00', 'total_amount' => '188.00', 'booking_amount' => '50.00', 'paid_amount' => '50.00', 'outstanding_amount' => '138.00', 'calculation_snapshot' => []]);
        $order->payments()->create(['payment_type' => 'BOOKING_DEPOSIT', 'provider' => 'TEST', 'amount' => '50.00', 'currency' => 'MYR', 'status' => 'PAID']);
        app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        return [$order->fresh(), $cs];
    }
}
