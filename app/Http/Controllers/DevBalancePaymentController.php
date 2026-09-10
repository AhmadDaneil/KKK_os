<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Payments\TestPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevBalancePaymentController extends Controller
{
    public function create(
        Request $request,
        string $orderId,
        CreateBalancePaymentService $creator,
        TestPaymentGateway $gateway,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $order = Order::where('order_id', $orderId)->firstOrFail();

        $payment = $creator->create($order, number_format((float) $validated['amount'], 2, '.', ''));

        $gatewayData = $gateway->createBalancePayment($payment);

        return response()->json([
            'order_id' => $order->order_id,
            'payment' => [
                'id' => $payment->id,
                'type' => $payment->payment_type,
                'status' => $payment->fresh()->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $gatewayData['provider'],
                'provider_reference' => $gatewayData['provider_reference'],
                'payment_url' => $gatewayData['payment_url'],
            ],
        ], 201);
    }

    public function pay(
        int $paymentId,
        TestPaymentGateway $gateway,
        HandlePaymentCallbackService $handler,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $payment = PaymentTransaction::findOrFail($paymentId);

        if (! $payment->provider_reference) {
            $gateway->createBalancePayment($payment);
            $payment->refresh();
        }

        $verified = $gateway->verifyCallback([
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'TEST-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        $updated = $handler->handle($verified);

        return response()->json([
            'payment_id' => $updated->id,
            'payment_status' => $updated->status,
            'order_status' => $updated->order->status,
        ]);
    }
}
