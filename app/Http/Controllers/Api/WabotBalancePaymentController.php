<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\CreateBillplzBalancePaymentService;
use Illuminate\Http\JsonResponse;

class WabotBalancePaymentController extends Controller
{
    public function store(string $orderId, CreateBillplzBalancePaymentService $checkout): JsonResponse
    {
        $order = Order::query()
            ->where('source', 'WABOT')
            ->where('order_id', $orderId)
            ->where('booking_payment_status', 'PAID')
            ->firstOrFail();

        $checkoutUrl = $checkout->create($order);
        $payment = $order->payments()
            ->where('payment_type', 'BALANCE')
            ->where('provider', 'BILLPLZ')
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'order_id' => $order->order_id,
            'balance' => [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'checkout_url' => $checkoutUrl,
                'payment_options' => ['ONLINE_BANKING', 'DUITNOW_QR'],
            ],
        ]);
    }
}
