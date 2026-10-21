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

        $validated = $request->validate([
            'payment_method' => ['nullable', Rule::in($availablePaymentMethods)],
        ]);

        $order = $access->resolve($request, $orderId);
        $result = $checkout->create(
            $order,
            paymentMethod: $validated['payment_method'] ?? BillplzPaymentMethod::BILLPLZ,
        );

        return redirect()->away($result['payment_url']);
    }
}
