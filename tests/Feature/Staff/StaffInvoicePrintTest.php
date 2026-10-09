<?php

namespace Tests\Feature\Staff;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StaffInvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_created_only_after_booking_is_paid_and_priced(): void
    {
        $order = $this->pricedOrder(1, 'UNPAID');
        $service = app(SynchronizeOrderFinancialsService::class);

        $this->assertNull($service->synchronize($order));
        $this->assertDatabaseCount('invoices', 0);

        $order->update(['booking_payment_status' => 'PAID']);
        $invoice = $service->synchronize($order->fresh());

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('INV-'.$order->order_id, $invoice->invoice_number);
        $this->assertSame('300.00', $invoice->total_amount);
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertSame('200.00', $invoice->balance_due);
        $this->assertSame('PARTIALLY_PAID', $invoice->payment_status);

        $service->synchronize($order->fresh());
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_om_can_print_one_package_invoice_and_other_roles_are_forbidden(): void
    {
        $order = $this->pricedOrder(1, 'PAID');
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);
        $om = $this->staff(User::ROLE_OM);
        $designer = $this->staff(User::ROLE_DESIGNER);

        $this->actingAs($om)
            ->get(route('staff.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Muka hadapan invois')
            ->assertSee('INVOIS')
            ->assertSee('Invoice Customer')
            ->assertSee($order->order_id)
            ->assertSee('RM 300.00')
            ->assertSee('TERMA &amp; SYARAT', false);

        $this->actingAs($designer)
            ->get(route('staff.invoices.print', $invoice))
            ->assertForbidden();
    }

    public function test_two_package_invoice_contains_the_package_count(): void
    {
        $order = $this->pricedOrder(2, 'PAID');
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);
        $om = $this->staff(User::ROLE_OM);

        $this->actingAs($om)
            ->get(route('staff.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Tempahan kad kahwin (2 pakej)');
    }

    public function test_om_can_download_invoice_as_a_real_pdf(): void
    {
        $order = $this->pricedOrder(2, 'PAID');
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        $response = $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_designer_cannot_download_invoice_pdf(): void
    {
        $order = $this->pricedOrder(1, 'PAID');
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        $this->actingAs($this->staff(User::ROLE_DESIGNER))
            ->get(route('staff.invoices.download', $invoice))
            ->assertForbidden();
    }

    public function test_order_header_shows_download_button_when_invoice_exists(): void
    {
        $order = $this->pricedOrder(1, 'PAID');
        app(SynchronizeOrderFinancialsService::class)->synchronize($order);

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee(route('staff.invoices.download', $order->invoice));
    }

    public function test_order_header_hides_download_button_until_invoice_exists(): void
    {
        $order = $this->pricedOrder(1, 'PAID');

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertDontSee('Download Invoice');
    }

    public function test_paid_order_without_price_snapshot_does_not_issue_invoice(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Unpriced Order',
        ]);
        $order->update(['booking_payment_status' => 'PAID']);

        $this->assertNull(app(SynchronizeOrderFinancialsService::class)->synchronize($order));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_route_returns_not_found_before_booking_is_paid(): void
    {
        $order = $this->pricedOrder(1, 'UNPAID');
        $order->update(['booking_payment_status' => 'PAID']);
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);
        $order->update(['booking_payment_status' => 'PENDING']);

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.invoices.print', $invoice))
            ->assertNotFound();
    }

    public function test_paid_amount_cannot_exceed_priced_order_total(): void
    {
        $order = $this->pricedOrder(1, 'PAID', '350.00');

        $this->expectException(RuntimeException::class);
        app(SynchronizeOrderFinancialsService::class)->synchronize($order);
    }

    private function pricedOrder(int $packageCount, string $bookingStatus, string $paidAmount = '100.00'): Order
    {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => 'Invoice Customer',
        ];

        if ($packageCount === 1) {
            $data['side'] = 'LELAKI';
        }

        $order = app(CreateOrderService::class)->create($data);
        $order->update([
            'status' => 'READY_FOR_DESIGN',
            'booking_payment_status' => $bookingStatus,
            'customer_email' => 'invoice@example.test',
            'customer_phone' => '0123456789',
        ]);
        $order->forceFill(['card_quantity' => 200])->save();

        $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'MANUAL_QR',
            'amount' => $paidAmount,
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);

        $order->pricingSnapshot()->create([
            'currency' => 'MYR',
            'subtotal' => '300.00',
            'postage_amount' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => '300.00',
            'booking_amount' => '100.00',
            'paid_amount' => '0.00',
            'outstanding_amount' => '300.00',
            'calculation_snapshot' => ['catalog' => 'test-fixture'],
        ]);

        return $order->fresh();
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
