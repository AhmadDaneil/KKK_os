<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingPaymentReturnController extends Controller
{
    public function show(
        Request $request,
        string $reference,
        CustomerOrderSessionAccessService $access,
    ): View {
        $payment = PaymentTransaction::query()
            ->where('public_reference', $reference)
            ->where('payment_type', 'BOOKING_DEPOSIT')
            ->with('order')
            ->firstOrFail();

        if ($payment->status === 'PAID') {
            $access->establishFromVerifiedOrder($request, $payment->order);
        }

        return view('public.booking-payment-return', [
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }
}
