<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\BillplzPaymentMethod;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerBookingPaymentController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        CreateBillplzBookingPaymentService $checkout,
    ): RedirectResponse {
        abort_unless(config('services.billplz.enabled'), 404);

        $availablePaymentMethods = [BillplzPaymentMethod::BILLPLZ];
        if (config('services.billplz.duitnow_qr_enabled')) {
            $availablePaymentMethods[] = BillplzPaymentMethod::DUITNOW_QR;
        }

        $order = $access->resolve($request, $orderId);
        $isFullTestPayment = $order->source === 'TEST' && $order->payment_plan === 'FULL';

        $rules = [
            'payment_method' => ['nullable', Rule::in($availablePaymentMethods)],
        ];

        if ($isFullTestPayment) {
            $rules['responsibility_acknowledged'] = ['required', 'accepted'];
            $rules['post_confirmation_liability_acknowledged'] = ['required', 'accepted'];
        }

        $validated = $request->validate($rules);
        $confirmationAcknowledgements = $isFullTestPayment
            ? [
                'responsibility_acknowledged' => true,
                'post_confirmation_liability_acknowledged' => true,
            ]
            : [];

        $result = $checkout->create(
            $order,
            paymentMethod: $validated['payment_method'] ?? BillplzPaymentMethod::BILLPLZ,
            confirmationAcknowledgements: $confirmationAcknowledgements,
        );

        return redirect()->away($result['payment_url']);
    }
}
