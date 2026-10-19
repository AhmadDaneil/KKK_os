<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\BillplzPaymentGateway;
use App\Services\Payments\HandlePaymentCallbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class BookingPaymentReturnController extends Controller
{
    public function show(
        Request $request,
        string $reference,
        CustomerOrderSessionAccessService $access,
        BillplzPaymentGateway $gateway,
        HandlePaymentCallbackService $handler,
    ): View {
        $payment = PaymentTransaction::query()
            ->where('public_reference', $reference)
            ->where('payment_type', 'BOOKING_DEPOSIT')
            ->with('order')
            ->firstOrFail();

        if ($payment->status === 'PENDING' && $payment->provider === 'BILLPLZ') {
            try {
                $payment = $handler->handle($gateway->retrievePaymentStatus($payment));
                $payment->load('order');
            } catch (Throwable $exception) {
                Log::warning('Billplz return reconciliation did not complete.', [
                    'payment_id' => $payment->id,
                    'reason' => $exception instanceof \App\Services\Payments\BillplzPaymentException
                        ? $exception->reason
                        : 'unexpected_error',
                ]);
            }
        }

        if ($payment->status === 'PAID') {
            $access->establishFromVerifiedOrder($request, $payment->order);
        }

        return view('public.booking-payment-return', [
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }
}
