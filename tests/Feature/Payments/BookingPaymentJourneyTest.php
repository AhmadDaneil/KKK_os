<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingPaymentJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        config()->set('app.url', 'https://167.71.196.30');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.x_signature_key', 'sandbox-signature');
        config()->set('services.billplz.collection_id', 'sandbox-collection');
    }

    public function test_one_package_booking_payment_activates_reference_invoice_dashboard_and_receipt(): void
    {
        $order = $this->createOrder(1);
        $this->fakeBill('booking-one', 5000);
        $checkout = app(CreateBillplzBookingPaymentService::class)->create($order);

        $this->assertSame('50.00', $checkout['payment']->amount);
        $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
            'id' => 'booking-one', 'collection_id' => 'sandbox-collection', 'paid' => 'true',
            'state' => 'paid', 'amount' => '5000', 'paid_amount' => '5000',
            'paid_at' => '2026-10-14 12:00:00 +0800',
        ]))->assertOk();

        $order = $order->fresh();
        $payment = $checkout['payment']->fresh();
        $this->assertSame('PAID', $order->booking_payment_status);
        $this->assertMatchesRegularExpression('/^KKK\d{6}-001$/', $order->customer_reference);
        $this->assertNotNull($order->dashboard_activated_at);
        $this->assertNotEmpty($payment->metadata['customer_access']['dashboard_url_encrypted']);
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'invoice_number' => $order->customer_reference,
            'amount_paid' => '50.00',
        ]);
        $this->assertDatabaseCount('order_access_tokens', 1);

        $this->get(route('payments.booking.return', ['reference' => $payment->public_reference]))
            ->assertOk()
            ->assertSee($order->customer_reference)
            ->assertSee(route('orders.dashboard', ['orderId' => $order->order_id]));
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))->assertOk();
    }

    public function test_two_package_booking_amount_is_one_hundred_ringgit(): void
    {
        $order = $this->createOrder(2);
        $this->fakeBill('booking-two', 10000);
        $checkout = app(CreateBillplzBookingPaymentService::class)->create($order);

        $this->assertSame('100.00', $checkout['payment']->amount);
        $this->assertSame('https://www.billplz-sandbox.com/bills/booking-two', $checkout['payment_url']);
    }

    public function test_return_page_reconciles_paid_one_package_bill_when_callback_is_delayed(): void
    {
        $order = $this->createOrder(1);
        $this->fakeBillThenPaidStatus('booking-reconcile-one', 5000);
        $payment = app(CreateBillplzBookingPaymentService::class)->create($order)['payment'];

        $this->get(route('payments.booking.return', ['reference' => $payment->public_reference]))
            ->assertOk()
            ->assertSee('Payment successful')
            ->assertSee(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('PAID', $order->fresh()->booking_payment_status);
        $this->assertDatabaseCount('order_access_tokens', 1);
    }

    public function test_return_page_reconciles_paid_two_package_bill_when_callback_is_delayed(): void
    {
        $order = $this->createOrder(2);
        $this->fakeBillThenPaidStatus('booking-reconcile-two', 10000);
        $payment = app(CreateBillplzBookingPaymentService::class)->create($order)['payment'];

        $this->get(route('payments.booking.return', ['reference' => $payment->public_reference]))
            ->assertOk()
            ->assertSee('Payment successful');

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('PAID', $order->fresh()->booking_payment_status);
        $this->assertDatabaseCount('order_access_tokens', 1);
    }

    public function test_trusted_http_sandbox_bill_url_is_upgraded_to_https(): void
    {
        $order = $this->createOrder(1);
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => 'booking-http-url',
            'collection_id' => 'sandbox-collection',
            'amount' => 5000,
            'url' => 'http://www.billplz-sandbox.com/bills/booking-http-url',
        ])]);

        $checkout = app(CreateBillplzBookingPaymentService::class)->create($order);

        $this->assertSame(
            'https://www.billplz-sandbox.com/bills/booking-http-url',
            $checkout['payment_url'],
        );
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $order = $this->createOrder(1);
        $this->fakeBill('booking-repeat', 5000);
        app(CreateBillplzBookingPaymentService::class)->create($order);
        $payload = $this->signedPayload([
            'id' => 'booking-repeat', 'collection_id' => 'sandbox-collection', 'paid' => 'true',
            'state' => 'paid', 'amount' => '5000', 'paid_amount' => '5000',
            'paid_at' => '2026-10-14 12:00:00 +0800',
        ]);

        $this->postJson(route('api.billplz-sandbox.callback'), $payload)->assertOk();
        $this->postJson(route('api.billplz-sandbox.callback'), $payload)->assertOk();

        $this->assertDatabaseCount('order_access_tokens', 1);
        $this->assertDatabaseCount('customer_reference_sequences', 1);
        $this->assertSame(3, $order->payments()->firstOrFail()->events()->count());
    }

    public function test_paid_callback_advances_confirmed_one_and_two_package_orders_to_design(): void
    {
        User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);

        foreach ([1, 2] as $packageCount) {
            $order = $this->createOrder($packageCount);
            $order->confirmation()->create([
                'confirmed_at' => now(),
                'confirmation_version' => 'v1',
            ]);
            $order->update([
                'status' => 'DETAILS_CONFIRMED',
                'details_confirmed_at' => now(),
            ]);
            $billId = "confirmed-{$packageCount}-package";
            $amountSen = $packageCount * 5000;
            $order->payments()->create([
                'payment_type' => 'BOOKING_DEPOSIT',
                'provider' => 'BILLPLZ',
                'provider_reference' => $billId,
                'amount' => number_format($amountSen / 100, 2, '.', ''),
                'currency' => 'MYR',
                'status' => 'PENDING',
            ]);

            $this->postJson(route('api.billplz-sandbox.callback'), $this->signedPayload([
                'id' => $billId,
                'collection_id' => 'sandbox-collection',
                'paid' => 'true',
                'state' => 'paid',
                'amount' => (string) $amountSen,
                'paid_amount' => (string) $amountSen,
                'paid_at' => '2026-10-19 09:50:00 +0800',
            ]))->assertOk();

            $this->assertSame('PAID', $order->fresh()->booking_payment_status);
            $this->assertSame('READY_FOR_DESIGN', $order->fresh()->status);
            $this->assertSame($packageCount, $order->mergeJobs()->count());
            $this->assertSame($packageCount, $order->designJobs()->count());
            $this->assertDatabaseHas('order_status_events', [
                'order_id' => $order->id,
                'event_type' => 'BOOKING_PAYMENT_CONFIRMED',
                'from_status' => 'DETAILS_CONFIRMED',
                'to_status' => 'READY_FOR_DESIGN',
            ]);
        }
    }

    private function createOrder(int $packageCount): Order
    {
        return app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'package_format' => $packageCount === 2 ? 'SEPARATE' : null,
            'first_event_side' => $packageCount === 2 ? 'LELAKI' : null,
            'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Pelanggan Booking',
            'customer_email' => 'booking@example.test',
            'customer_phone' => '60123456789',
        ]);
    }

    private function fakeBill(string $billId, int $amountSen): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response([
            'id' => $billId,
            'collection_id' => 'sandbox-collection',
            'amount' => $amountSen,
            'url' => 'https://www.billplz-sandbox.com/bills/'.$billId,
        ])]);
    }

    private function fakeBillThenPaidStatus(string $billId, int $amountSen): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.billplz-sandbox.com/*' => Http::sequence()
                ->push([
                    'id' => $billId,
                    'collection_id' => 'sandbox-collection',
                    'amount' => $amountSen,
                    'url' => 'https://www.billplz-sandbox.com/bills/'.$billId,
                ])
                ->push([
                    'id' => $billId,
                    'collection_id' => 'sandbox-collection',
                    'paid' => true,
                    'state' => 'paid',
                    'amount' => $amountSen,
                    'paid_amount' => $amountSen,
                    'paid_at' => '2026-10-19 09:50:00 +0800',
                ]),
        ]);
    }

    private function signedPayload(array $payload): array
    {
        $parameters = $payload;
        uksort($parameters, static fn (string $left, string $right): int => strcasecmp($left, $right));
        $source = implode('|', array_map(
            static fn (string $key, mixed $value): string => $key.(string) $value,
            array_keys($parameters), array_values($parameters),
        ));
        $payload['x_signature'] = hash_hmac('sha256', $source, 'sandbox-signature');

        return $payload;
    }
}
