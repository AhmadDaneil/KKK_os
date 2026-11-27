<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PackingJob;
use App\Models\User;
use App\Services\Orders\AddStaffOrderAddOnService;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\OrderAddOnCatalogService;
use App\Services\Packing\BuildPackingChecklistService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffOrderAddOnTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_matches_requested_prices_and_minimums(): void
    {
        $options = app(OrderAddOnCatalogService::class)->options();
        $prices = [
            'BANTING_2X4' => 1900, 'BANNER_3X6' => 3900, 'ARROW_SIGN_A4_2PCS' => 300,
            'RUBBER_STAMP_SELF_INK' => 4900, 'RUBBER_STAMP_PLASTIC' => 3500, 'WOODEN_HANGER_ENGRAVED' => 1000,
            'MOCKUP_CHEQUE_GOLD_ROSEGOLD_SILVER' => 4900, 'MOCKUP_CHEQUE_CRYSTAL_TRANSPARENT' => 2900,
            'WELCOME_BOARD_CRYSTAL_TRANSPARENT' => 5900, 'WELCOME_BOARD_FROSTED_BLACK_WHITE' => 7900,
            'WELCOME_BOARD_MIRROR_GOLD_ROSEGOLD_SILVER' => 9900, 'WELCOME_BOARD_WOODEN_STAND' => 3900,
            'WELCOME_BOARD_STEEL_STAND' => 5900, 'BANTING_STEEL_STAND' => 3900, 'WEDDING_BOX_READY_MADE' => 39,
            'GUEST_BOOK_A4' => 1900, 'BUTTON_BADGE_READY_MADE' => 150, 'HOME_SIGNAGE_2_LAYER' => 7900, 'HOME_SIGNAGE_1_LAYER' => 4900,
        ];
        $this->assertCount(19, $options);
        foreach ($prices as $code => $cents) {
            $this->assertSame($cents, $options[$code]['base_unit_price_cents']);
        }
        $this->assertSame(7400, $options['WELCOME_BOARD_CRYSTAL_TRANSPARENT']['unit_price_cents']);
        $this->assertSame(10, $options['BUTTON_BADGE_READY_MADE']['min_quantity']);
    }

    public function test_customer_service_can_add_items_to_existing_invoice_once_with_server_prices(): void
    {
        [$order, $cs] = $this->pricedOrder();
        $invoice = $order->invoice;
        $this->actingAs($cs, 'staff')->get(route('staff.orders.show', $order->order_id))
            ->assertSee('Add-On')->assertSee('Wedding Box Ready Made')->assertSee('RM 0.39');
        $data = ['code' => 'BANTING_2X4', 'quantity' => 2, 'unit_price' => '0.01', 'request_id' => (string) Str::uuid()];

        $this->post(route('staff.orders.add-ons.store', $order), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('staff.orders.add-ons.store', $order), $data)->assertRedirect()->assertSessionHasNoErrors();

        $item = $order->items()->where('item_type', 'ADDON')->sole();
        $this->assertSame('19.00', $item->unit_price);
        $this->assertSame('38.00', $item->line_total);
        $this->assertSame('226.00', $order->pricingSnapshot()->first()->total_amount);
        $invoice->refresh();
        $this->assertSame('50.00', $invoice->amount_paid);
        $this->assertSame('176.00', $invoice->balance_due);
        $this->assertSame('PARTIALLY_PAID', $invoice->payment_status);
        $this->assertSame($invoice->id, $order->invoice()->first()->id);
        $this->assertSame('READY_FOR_DESIGN', $order->fresh()->status);
        $this->assertSame(1, $order->statusEvents()->where('event_type', 'ADDON_ADDED')->count());
        $this->assertContains($item->description, array_column(app(BuildPackingChecklistService::class)->build($order), 'description'));
    }

    public function test_welcome_board_postage_and_box_decimal_prices_are_exact(): void
    {
        [$order, $cs] = $this->pricedOrder();
        $this->actingAs($cs, 'staff');
        $this->add($order, 'WELCOME_BOARD_CRYSTAL_TRANSPARENT', 2, ['notes' => 'Crystal'])->assertSessionHasNoErrors();
        $board = $order->items()->where('item_code', 'WELCOME_BOARD_CRYSTAL_TRANSPARENT')->firstOrFail();
        $this->assertSame('74.00', $board->unit_price);
        $this->assertSame('148.00', $board->line_total);
        $this->assertSame('15.00', $board->metadata['welcome_board_postage_per_unit']);
        $this->add($order, 'WEDDING_BOX_READY_MADE', 3)->assertSessionHasNoErrors();
        $this->assertSame('1.17', $order->items()->where('item_code', 'WEDDING_BOX_READY_MADE')->value('line_total'));
        $this->assertSame('337.17', $order->pricingSnapshot()->value('total_amount'));
    }

    public function test_badge_minimum_and_invalid_code_are_rejected_without_financial_changes(): void
    {
        [$order, $cs] = $this->pricedOrder();
        $this->actingAs($cs, 'staff');
        $this->add($order, 'BUTTON_BADGE_READY_MADE', 9)->assertSessionHasErrors('quantity');
        $this->add($order, 'INVENTED_PRODUCT', 1)->assertSessionHasErrors('code');
        $this->assertSame(0, $order->items()->where('item_type', 'ADDON')->count());
        $this->assertSame('188.00', $order->pricingSnapshot()->value('total_amount'));
        $this->add($order, 'BUTTON_BADGE_READY_MADE', 10)->assertSessionHasNoErrors();
        $this->assertSame('15.00', $order->items()->where('item_code', 'BUTTON_BADGE_READY_MADE')->value('line_total'));
    }

    #[DataProvider('disallowedRoles')]
    public function test_design_and_production_roles_cannot_add_items(string $role): void
    {
        [$order] = $this->pricedOrder();
        $staff = User::factory()->create(['role' => $role, 'is_active' => true]);
        $this->actingAs($staff, 'staff');

        $this->add($order)->assertForbidden();

        $this->assertSame(0, $order->items()->where('item_type', 'ADDON')->count());
        $this->assertSame('188.00', $order->pricingSnapshot()->value('total_amount'));
    }

    public static function disallowedRoles(): array
    {
        return [['DESIGNER'], ['DIGITAL_DESIGNER'], ['PRODUCTION']];
    }

    #[DataProvider('closedStatuses')]
    public function test_closed_or_packing_orders_do_not_accept_addons(string $status): void
    {
        [$order, $cs] = $this->pricedOrder();
        $order->update(['status' => $status]);
        $this->actingAs($cs, 'staff');

        $this->add($order)->assertSessionHasErrors('add_on');

        $this->assertSame(0, $order->items()->where('item_type', 'ADDON')->count());
    }

    public static function closedStatuses(): array
    {
        return [['COMPLETED'], ['CANCELLED'], ['ARCHIVED'], ['PACKING'], ['PACKED'], ['SHIPPED']];
    }

    public function test_additions_are_blocked_while_balance_checkout_is_pending(): void
    {
        [$order, $cs] = $this->pricedOrder();
        $order->payments()->create(['payment_type' => 'BALANCE', 'provider' => 'TEST', 'amount' => '138.00', 'currency' => 'MYR', 'status' => 'PENDING']);
        $this->actingAs($cs, 'staff');

        $this->add($order)->assertSessionHasErrors('add_on');

        $this->assertSame('188.00', $order->pricingSnapshot()->value('total_amount'));
    }

    public function test_items_added_before_confirmation_are_included_when_pricing_snapshot_is_created(): void
    {
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        $cs = User::factory()->create(['role' => User::ROLE_CUSTOMER_SERVICE, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'package_type' => 'JELATA', 'source' => 'TEST']);
        $order->packageSides()->update(['card_quantity' => 100]);
        $order->forceFill(['card_quantity' => 100])->save();
        app(AddStaffOrderAddOnService::class)->add($order, $cs, 'WEDDING_BOX_READY_MADE', 1, null, (string) Str::uuid());

        $snapshot = app(BuildOrderPricingService::class)->createSnapshot($order->fresh());

        $this->assertSame('89.39', $snapshot->total_amount);
        $this->assertSame('89.39', $snapshot->subtotal);
        $this->assertSame('0.39', $snapshot->calculation_snapshot['add_on_amount']);
    }

    public function test_extra_balance_can_be_paid_without_rewinding_printing_progress(): void
    {
        [$order, $cs] = $this->pricedOrder(true);
        $order->update(['status' => 'READY_FOR_PACKING']);
        $this->actingAs($cs, 'staff');
        $this->add($order)->assertSessionHasNoErrors();
        config([
            'app.url' => 'https://167.71.196.30', 'services.billplz.enabled' => true,
            'services.billplz.secret_key' => 'test-secret', 'services.billplz.collection_id' => 'test-collection',
            'services.billplz.base_url' => 'https://www.billplz-sandbox.com/api',
        ]);
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['id' => 'addon-bill', 'collection_id' => 'test-collection', 'amount' => 1900, 'url' => 'https://www.billplz-sandbox.com/bills/addon-bill'])]);
        $this->post(route('public.orders.progress.lookup'), ['order_id' => $order->order_id])
            ->assertSee('Order balance due')->assertDontSee('Full payment confirmed');

        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]))
            ->assertRedirect('https://www.billplz-sandbox.com/bills/addon-bill');

        $payment = $order->payments()->where('provider_reference', 'addon-bill')->firstOrFail();
        $this->assertSame('19.00', $payment->amount);
        $this->assertSame('READY_FOR_PACKING', $order->fresh()->status);
        app(HandlePaymentCallbackService::class)->handle(['provider' => 'BILLPLZ', 'provider_reference' => 'addon-bill', 'status' => 'PAID', 'amount_sen' => 1900]);
        $this->assertSame('READY_FOR_PACKING', $order->fresh()->status);
        $this->assertSame('0.00', $order->pricingSnapshot()->value('outstanding_amount'));
        $this->assertSame('207.00', $order->invoice()->value('amount_paid'));
    }

    public function test_packing_cannot_start_until_the_added_items_are_paid(): void
    {
        [$order, $cs] = $this->pricedOrder(true);
        $order->update(['status' => 'READY_FOR_PACKING']);
        $this->actingAs($cs, 'staff');
        $this->add($order)->assertSessionHasNoErrors();
        $om = User::factory()->create(['role' => User::ROLE_OM, 'is_active' => true]);
        $job = PackingJob::create(['order_id' => $order->id, 'status' => 'READY_FOR_PACKING', 'assigned_user_id' => $om->id]);
        $keys = array_column(app(BuildPackingChecklistService::class)->build($order->fresh()), 'key');

        $this->actingAs($om, 'staff')->post(route('staff.packing-jobs.start', $job), ['packing_checks' => array_fill_keys($keys, '1')])
            ->assertSessionHasErrors('packing_job');

        $this->assertSame('READY_FOR_PACKING', $job->fresh()->status);
    }

    public function test_a_late_old_payment_does_not_mark_the_new_addon_balance_as_paid(): void
    {
        [$order, $cs] = $this->pricedOrder();
        $order->update(['status' => 'DESIGN_APPROVED']);
        $order->payments()->create(['payment_type' => 'BALANCE', 'provider' => 'TEST', 'provider_reference' => 'old-failed-bill', 'amount' => '138.00', 'currency' => 'MYR', 'status' => 'FAILED']);
        $this->actingAs($cs, 'staff');
        $this->add($order)->assertSessionHasNoErrors();

        app(HandlePaymentCallbackService::class)->handle(['provider' => 'TEST', 'provider_reference' => 'old-failed-bill', 'status' => 'PAID']);

        $this->assertSame('BALANCE_PENDING', $order->fresh()->status);
        $this->assertSame('19.00', $order->pricingSnapshot()->value('outstanding_amount'));
        $this->assertSame('188.00', $order->invoice()->value('amount_paid'));
    }

    #[DataProvider('managementPortals')]
    public function test_management_can_also_use_the_addon_control(string $portal, string $role): void
    {
        [$order] = $this->pricedOrder();
        $manager = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($manager, $portal)->get(route($portal.'.orders.show', $order->order_id))
            ->assertSee('Add Add-On');
        $this->post(route($portal.'.orders.add-ons.store', $order), ['code' => 'BANNER_3X6', 'quantity' => 1, 'request_id' => (string) Str::uuid()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('227.00', $order->pricingSnapshot()->value('total_amount'));
    }

    public static function managementPortals(): array
    {
        return [['admin', User::ROLE_ADMIN], ['staff', User::ROLE_OM]];
    }

    public function test_a_duplicate_old_balance_callback_keeps_existing_printing_progress(): void
    {
        [$order, $cs] = $this->pricedOrder(true);
        $order->update(['status' => 'PRINTING']);
        $payment = $order->payments()->where('payment_type', 'BALANCE')->firstOrFail();
        $payment->update(['provider_reference' => 'old-paid-balance']);
        $this->actingAs($cs, 'staff');
        $this->add($order)->assertSessionHasNoErrors();

        app(HandlePaymentCallbackService::class)->handle(['provider' => 'TEST', 'provider_reference' => 'old-paid-balance', 'status' => 'PAID']);

        $this->assertSame('PRINTING', $order->fresh()->status);
        $this->assertSame('19.00', $order->pricingSnapshot()->value('outstanding_amount'));
    }

    private function add(Order $order, string $code = 'BANTING_2X4', int $quantity = 1, array $extra = []): TestResponse
    {
        return $this->post(route('staff.orders.add-ons.store', $order), array_merge(['code' => $code, 'quantity' => $quantity, 'request_id' => (string) Str::uuid()], $extra));
    }

    /** @return array{Order, User} */
    private function pricedOrder(bool $fullyPaid = false): array
    {
        $cs = User::factory()->create(['role' => User::ROLE_CUSTOMER_SERVICE, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1, 'side' => 'LELAKI', 'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Add-On Customer', 'customer_email' => 'addon@example.test', 'customer_phone' => '0123456789',
        ]);
        $order->packageSides()->update(['card_quantity' => 200]);
        $order->forceFill(['status' => 'READY_FOR_DESIGN', 'booking_payment_status' => 'PAID', 'card_quantity' => 200])->save();
        $order->pricingSnapshot()->create([
            'currency' => 'MYR', 'subtotal' => '188.00', 'total_amount' => '188.00', 'booking_amount' => '50.00',
            'paid_amount' => $fullyPaid ? '188.00' : '50.00', 'outstanding_amount' => $fullyPaid ? '0.00' : '138.00', 'calculation_snapshot' => [],
        ]);
        $order->payments()->create(['payment_type' => 'BOOKING_DEPOSIT', 'provider' => 'TEST', 'amount' => '50.00', 'currency' => 'MYR', 'status' => 'PAID']);
        if ($fullyPaid) {
            $order->payments()->create(['payment_type' => 'BALANCE', 'provider' => 'TEST', 'amount' => '138.00', 'currency' => 'MYR', 'status' => 'PAID']);
        }
        app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        return [$order->fresh(['invoice']), $cs];
    }
}
