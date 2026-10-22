<?php

namespace Tests\Feature\Staff;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\CreateOrderService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
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
        $this->assertSame($order->fresh()->customer_reference, $invoice->invoice_number);
        $this->assertMatchesRegularExpression('/^KKK\d{6}-\d{3}$/', $invoice->invoice_number);
        $this->assertSame('300.00', $invoice->total_amount);
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertSame('200.00', $invoice->balance_due);
        $this->assertSame('PARTIALLY_PAID', $invoice->payment_status);

        $service->synchronize($order->fresh());
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_refreshing_an_existing_legacy_invoice_keeps_its_invoice_number(): void
    {
        $order = $this->pricedOrder(1, 'PAID');
        $service = app(SynchronizeOrderFinancialsService::class);
        $invoice = $service->synchronize($order);
        $legacyInvoiceNumber = 'INV-'.$invoice->invoice_number;

        $invoice->update(['invoice_number' => $legacyInvoiceNumber]);
        $order->payments()->where('payment_type', 'BOOKING_DEPOSIT')->update(['amount' => '150.00']);

        $refreshedInvoice = $service->synchronize($order->fresh());

        $this->assertSame($legacyInvoiceNumber, $refreshedInvoice->invoice_number);
        $this->assertSame('150.00', $refreshedInvoice->amount_paid);
        $this->assertSame('150.00', $refreshedInvoice->balance_due);
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
            ->assertSee('Invoice front page')
            ->assertSee('INVOICE')
            ->assertSee('Invoice Customer')
            ->assertSee($order->order_id)
            ->assertSee('RM 300.00')
            ->assertSee('TR0271374-H')
            ->assertSee('massaempirehq@gmail.com')
            ->assertSee('3 &amp; 3A, Jalan Malinja 3', false)
            ->assertSee('MAYBANK')
            ->assertSee('569963039295')
            ->assertSee('TERMS &amp; CONDITIONS / ORDER PROCESS', false);

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
            ->assertSee('Wedding card order (2 packages)');
    }

    public function test_puteri_manis_invoice_lists_package_contents_and_free_items(): void
    {
        $order = $this->pricedOrder(1, 'PAID');
        $order->update(['package_type' => 'PUTERI_MANIS']);
        $side = $order->packageSides()->firstOrFail();
        $side->update(['card_quantity' => 200]);
        $side->design()->updateOrCreate([], ['design_code' => 'CKP-006']);
        $side->event()->updateOrCreate([], ['event_date' => '2026-12-20']);
        $order->forceFill(['card_quantity' => 200])->save();
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order->fresh());

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Pakej Puteri Manis')
            ->assertSee('Kad Kahwin 4x6 200 pcs')
            ->assertSee('White Envelope 4x6 200 pcs')
            ->assertSee('Banting 2x4 1 pc')
            ->assertSee('Banner 3x6 1 pc')
            ->assertSee('Arrow A4 4 pcs')
            ->assertSee('Sticker 3cm 150 pcs')
            ->assertSee('E-Card (PDF File)')
            ->assertSee('FREE Alive Card Premium')
            ->assertSee('FREE Motion Video')
            ->assertSee('FREE Wooden Hanger 1 pc')
            ->assertSee('Male: 200 | Female: 0 | Total: 200')
            ->assertSee('Booking Date')
            ->assertSee('Event Date')
            ->assertSee('Design Code')
            ->assertSee('CKP-006')
            ->assertDontSee('Kad Kahwin 4x6 200 pcs;');
    }

    public function test_invoice_shows_rm50_and_rm100_deposit_examples(): void
    {
        foreach ([
            ['deposit' => '50.00', 'balance' => '250.00'],
            ['deposit' => '100.00', 'balance' => '200.00'],
        ] as $example) {
            $order = $this->pricedOrder(1, 'PAID', $example['deposit']);
            $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order);

            $this->actingAs($this->staff(User::ROLE_OM))
                ->get(route('staff.invoices.print', $invoice))
                ->assertOk()
                ->assertSee('Deposit')
                ->assertSee('RM '.$example['deposit'])
                ->assertSee('RM '.$example['balance']);
        }
    }

    public function test_invoice_keeps_booking_deposit_separate_from_paid_balance(): void
    {
        $order = $this->pricedOrder(1, 'PAID', '50.00');
        $order->payments()->create([
            'payment_type' => 'BALANCE',
            'provider' => 'MANUAL_QR',
            'amount' => '250.00',
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order->fresh());

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Deposit')
            ->assertSee('RM 50.00')
            ->assertSee('Balance Due')
            ->assertSee('RM 0.00')
            ->assertDontSee('Payment Received');
    }

    public function test_jelata_two_package_invoice_lists_contents_without_puteri_manis_freebies(): void
    {
        $order = $this->pricedOrder(2, 'PAID');
        $order->update(['package_type' => 'JELATA']);
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order->fresh());

        $this->actingAs($this->staff(User::ROLE_OM))
            ->get(route('staff.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Kad Kahwin 4x6 200 pcs')
            ->assertSee('Pakej Jelata')
            ->assertSee('Live Card')
            ->assertSee('Sampul 200 pcs')
            ->assertDontSee('FREE Motion Video')
            ->assertDontSee('FREE Wooden Hanger');
    }

    public function test_postage_quote_uses_package_quantity_and_destination_region(): void
    {
        $this->seed(KkkPackagePriceCatalogSeeder::class);

        $onePackage = $this->pricedOrder(1, 'PAID');
        $onePackage->update(['package_type' => 'PUTERI_MANIS']);
        $onePackage->packageSides()->firstOrFail()->update(['card_quantity' => 200]);
        $onePackage->fulfilment()->update([
            'method' => 'COURIER',
            'shipping_address' => 'Sekinchan, Selangor',
        ]);
        $onePackage->forceFill(['card_quantity' => 200])->save();

        $peninsularQuote = app(BuildOrderPricingService::class)->quote($onePackage->fresh());
        $this->assertSame('25.00', $peninsularQuote['postage_amount']);
        $this->assertSame('213.00', $peninsularQuote['total_amount']);
        $this->assertSame('Postage Semenanjung - Puteri Manis 200 pcs', $peninsularQuote['delivery']['description']);

        $twoPackages = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Two Package Customer',
        ]);
        $twoPackages->packageSides()->where('side', 'LELAKI')->firstOrFail()->update(['card_quantity' => 30]);
        $twoPackages->packageSides()->where('side', 'PEREMPUAN')->firstOrFail()->update(['card_quantity' => 50]);
        $twoPackages->forceFill(['card_quantity' => 80])->save();
        $twoPackages->fulfilment()->update([
            'method' => 'COURIER',
            'shipping_address' => 'Kota Kinabalu, Sabah',
        ]);

        $eastMalaysiaQuote = app(BuildOrderPricingService::class)->quote($twoPackages->fresh());
        $this->assertSame('70.00', $eastMalaysiaQuote['postage_amount']);
        $this->assertSame('296.00', $eastMalaysiaQuote['total_amount']);
        $this->assertSame('Postage Sabah/Sarawak - Puteri Manis 80 pcs', $eastMalaysiaQuote['delivery']['description']);
    }

    public function test_postage_is_saved_in_pricing_snapshot_and_invoice_balance(): void
    {
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Postage Customer',
        ]);
        $order->packageSides()->firstOrFail()->update(['card_quantity' => 200]);
        $order->forceFill([
            'card_quantity' => 200,
            'booking_payment_status' => 'PAID',
        ])->save();
        $order->fulfilment()->update([
            'method' => 'COURIER',
            'recipient_name' => 'Postage Customer',
            'recipient_phone' => '0123456789',
            'shipping_address' => 'Sekinchan, Selangor',
        ]);
        $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'MANUAL_QR',
            'amount' => '50.00',
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);

        $snapshot = app(BuildOrderPricingService::class)->createSnapshot($order->fresh());
        $invoice = app(SynchronizeOrderFinancialsService::class)->synchronize($order->fresh());

        $this->assertSame('188.00', $snapshot->subtotal);
        $this->assertSame('25.00', $snapshot->postage_amount);
        $this->assertSame('213.00', $snapshot->total_amount);
        $this->assertSame('213.00', $invoice->total_amount);
        $this->assertSame('163.00', $invoice->balance_due);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_type' => 'DELIVERY',
            'description' => 'Postage Semenanjung - Puteri Manis 200 pcs',
            'line_total' => '25.00',
        ]);
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
