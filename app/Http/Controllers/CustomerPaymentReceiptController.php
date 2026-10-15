<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentReceiptController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        PaymentTransaction $payment,
        CustomerOrderSessionAccessService $access,
    ): View {
        abort_unless($payment->order?->order_id === $orderId && $payment->status === 'PAID', 404);
        abort_unless($request->hasValidSignature() || $access->hasAccess($request, $payment->order), 403);

        return view('orders.payment-receipt', [
            'order' => $payment->order,
            'payment' => $payment,
        ]);
    }
}
