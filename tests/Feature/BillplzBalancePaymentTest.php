<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillplzBalancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://167.71.196.30');
        config()->set('services.billplz.enabled', true);
        config()->set('services.billplz.secret_key', 'billplz-sandbox-secret');
        config()->set('services.billplz.base_url', 'https://www.billplz-sandbox.com/api');
        config()->set('services.billplz.x_signature_key', 'billplz-sandbox-signature');
        config()->set('services.billplz.collection_id', 'sandbox-collection-001');
    }

    public function test_one_package_checkout_uses_snapshot_balance_as_sen(): void
    {
        $order = $this->createApprovedOrder(1, '300.00', '50.00', '250.00');
        $this->fakeBillCreation('bill-one-package', 25000);

        $this->lookupOrder($order)
            ->assertOk();

        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]), [
            'amount' => '1.00',
        ])->assertRedirect('https://www.billplz-sandbox.com/bills/bill-one-package');

        Http::assertSent(fn (ClientRequest $request): bool => (int) $request->data()['amount'] === 25000
            && $request->data()['collection_id'] === 'sandbox-collection-001'
            && $request->data()['callback_url'] === 'https://167.71.196.30/api/v1/payments/billplz-sandbox/callback'
            && $request->data()['redirect_url'] === 'https://167.71.196.30/semak-progress?order_id='.$order->order_id
            && $request->data()['reference_1'] === $order->order_id
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('billplz-sandbox-secret:'))
        );

        $payment = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('payment_type', 'BALANCE')
            ->firstOrFail();

        $this->assertSame('BILLPLZ', $payment->provider);
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame('bill-one-package', $payment->provider_reference);
        $this->assertSame('BALANCE_PENDING', $order->fresh()->status);
    }

    public function test_two_package_checkout_uses_snapshot_balance_as_sen(): void
    {
        $order = $this->createApprovedOrder(2, '500.00', '100.00', '400.00');
        $this->fakeBillCreation('bill-two-packages', 40000);

        $this->lookupOrder($order)
            ->assertOk();

        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]))
            ->assertRedirect('https://www.billplz-sandbox.com/bills/bill-two-packages');

        Http::assertSent(fn (ClientRequest $request): bool => (int) $request->data()['amount'] === 40000);
    }

    public function test_repeat_checkout_reuses_the_existing_bill_without_creating_another(): void
    {
        $order = $this->createApprovedOrder(1, '300.00', '50.00', '250.00');
        $this->fakeBillCreation('bill-reused', 25000);

        $this->lookupOrder($order)
            ->assertOk();

        $checkoutRoute = route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]);
        $this->post($checkoutRoute)->assertRedirect('https://www.billplz-sandbox.com/bills/bill-reused');
        $this->post($checkoutRoute)->assertRedirect('https://www.billplz-sandbox.com/bills/bill-reused');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_checkout_does_not_create_a_second_payment_when_manual_balance_is_pending(): void
    {
        $order = $this->createApprovedOrder(1, '300.00', '50.00', '250.00');
        $order->payments()->create([
            'payment_type' => 'BALANCE',
            'provider' => 'MANUAL_QR',
            'amount' => '250.00',
            'currency' => 'MYR',
            'status' => 'PENDING',
        ]);
        Http::preventStrayRequests();

        $this->lookupOrder($order)
            ->assertOk();

        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]))
            ->assertSessionHasErrors('billplz');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_billplz_api_error_leaves_no_active_gateway_payment(): void
    {
        $order = $this->createApprovedOrder(1, '300.00', '50.00', '250.00');
        Http::preventStrayRequests();
        Http::fake([
            'www.billplz-sandbox.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->lookupOrder($order)
            ->assertOk();

        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]))
            ->assertSessionHasErrors('billplz');

        $payment = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('payment_type', 'BALANCE')
            ->firstOrFail();

        $this->assertSame('FAILED', $payment->status);
        $this->assertSame('FAILED', $payment->metadata['billplz']['checkout_status']);
        $this->assertSame('BALANCE_PENDING', $order->fresh()->status);
    }

    public function test_valid_signed_callback_marks_balance_and_order_paid(): void
    {
        $order = $this->createApprovedOrder(1, '300.00', '50.00', '250.00');
        $this->fakeBillCreation('bill-paid', 25000);

        $this->lookupOrder($order)
            ->assertOk();
        $this->post(route('orders.balance-payment.billplz.store', ['orderId' => $order->order_id]))
            ->assertRedirect('https://www.billplz-sandbox.com/bills/bill-paid');

        $payload = $this->signedPayload([
            'id' => 'bill-paid',
            'collection_id' => 'sandbox-collection-001',
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '25000',
            'paid_amount' => '25000',
            'paid_at' => '2026-10-13 16:00:00 +0800',
        ]);

        $this->postJson(route('api.billplz-sandbox.callback'), $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('payment_status', 'PAID');

        $payment = PaymentTransaction::query()
            ->where('provider', 'BILLPLZ')
            ->where('provider_reference', 'bill-paid')
            ->firstOrFail();

        $this->assertSame('PAID', $payment->status);
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame('0.00', $order->pricingSnapshot()->value('outstanding_amount'));
    }

    private function createApprovedOrder(int $packageCount, string $total, string $deposit, string $balance): Order
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'customer_name' => 'Billplz Sandbox Customer',
            'customer_email' => 'billplz@example.test',
            'customer_phone' => '0123456789',
        ]);

        $order->update([
            'status' => 'DESIGN_APPROVED',
            'booking_payment_status' => 'PAID',
        ]);
        $order->pricingSnapshot()->create([
            'currency' => 'MYR',
            'subtotal' => $total,
            'postage_amount' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => $total,
            'booking_amount' => $deposit,
            'paid_amount' => $deposit,
            'outstanding_amount' => $balance,
            'calculation_snapshot' => [],
        ]);
        $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT',
            'provider' => 'MANUAL_QR',
            'amount' => $deposit,
            'currency' => 'MYR',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    private function lookupOrder(Order $order): TestResponse
    {
        $url = app(GenerateOrderAccessLinkService::class)->generate($order);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $this->get($query === '' ? $path : $path.'?'.$query)->assertRedirect();

        return $this->get(route('orders.dashboard', ['orderId' => $order->order_id]));
    }

    private function fakeBillCreation(string $billId, int $amountSen): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.billplz-sandbox.com/*' => Http::response([
                'id' => $billId,
                'collection_id' => 'sandbox-collection-001',
                'amount' => $amountSen,
                'url' => 'https://www.billplz-sandbox.com/bills/'.$billId,
            ], 200),
        ]);
    }

    private function signedPayload(array $payload): array
    {
        $parameters = $payload;
        uksort($parameters, static fn (string $left, string $right): int => strcasecmp($left, $right));
        $source = implode('|', array_map(
            static fn (string $key, mixed $value): string => $key.(string) $value,
            array_keys($parameters),
            array_values($parameters),
        ));
        $payload['x_signature'] = hash_hmac('sha256', $source, 'billplz-sandbox-signature');

        return $payload;
    }
}
