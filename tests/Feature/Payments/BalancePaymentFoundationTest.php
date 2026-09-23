<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentTransaction;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BalancePaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_design_approved_order_can_create_balance_payment(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Payment Test',
        ]);

        $this->expectException(RuntimeException::class);

        app(CreateBalancePaymentService::class)->create($order, '100.00');
    }

    public function test_design_approved_order_creates_pending_balance_payment(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Payment Test',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        $payment = app(CreateBalancePaymentService::class)->create($order->fresh(), '250.00');

        $this->assertSame('BALANCE', $payment->payment_type);
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame('BALANCE_PENDING', $order->fresh()->status);
    }

    public function test_duplicate_creation_returns_existing_pending_payment(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'Two Package Payment',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        $first = app(CreateBalancePaymentService::class)->create($order->fresh(), '500.00');
        $second = app(CreateBalancePaymentService::class)->create($order->fresh(), '500.00');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_success_callback_marks_payment_and_order_paid(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
            'customer_name' => 'Paid Test',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        $payment = app(CreateBalancePaymentService::class)->create($order->fresh(), '300.00');

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'REF-001',
        ]);

        $updated = app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => 'REF-001',
            'provider_event_id' => 'EVENT-001',
            'status' => 'PAID',
            'raw_payload' => ['example' => true],
        ]);

        $this->assertSame('PAID', $updated->status);
        $this->assertNotNull($updated->paid_at);
        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Duplicate Callback',
        ]);

        $order->update(['status' => 'DESIGN_APPROVED']);

        $payment = app(CreateBalancePaymentService::class)->create($order->fresh(), '400.00');

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'REF-DUP',
        ]);

        $payload = [
            'provider' => 'TEST',
            'provider_reference' => 'REF-DUP',
            'provider_event_id' => 'EVENT-DUP',
            'status' => 'PAID',
        ];

        app(HandlePaymentCallbackService::class)->handle($payload);
        app(HandlePaymentCallbackService::class)->handle($payload);

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('payment_events', 2);
        $this->assertSame('PAID', PaymentTransaction::first()->status);
    }

    public function test_balance_payment_callback_updates_only_the_correct_order(): void
    {
        $orderA = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'T11 Order A',
        ]);

        $orderB = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
            'customer_name' => 'T11 Order B',
        ]);

        $orderA->update(['status' => 'DESIGN_APPROVED']);
        $orderB->update(['status' => 'DESIGN_APPROVED']);

        $paymentA = app(CreateBalancePaymentService::class)->create(
            $orderA->fresh(),
            '300.00'
        );

        $paymentB = app(CreateBalancePaymentService::class)->create(
            $orderB->fresh(),
            '400.00'
        );

        $paymentA->update([
            'provider' => 'TEST',
            'provider_reference' => 'T11-ORDER-A',
        ]);

        $paymentB->update([
            'provider' => 'TEST',
            'provider_reference' => 'T11-ORDER-B',
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => 'T11-ORDER-A',
            'provider_event_id' => 'T11-EVENT-A',
            'status' => 'PAID',
        ]);

        $this->assertSame('PAID', $paymentA->fresh()->status);
        $this->assertSame('PAID', $orderA->fresh()->status);

        $this->assertSame('PENDING', $paymentB->fresh()->status);
        $this->assertSame('BALANCE_PENDING', $orderB->fresh()->status);
    }

}
