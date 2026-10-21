<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\BillplzPaymentMethod;
use App\Services\Payments\CreateBillplzBalancePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerBillplzBalancePaymentController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        CreateBillplzBalancePaymentService $checkout,
    ): RedirectResponse {
        abort_unless(config('services.billplz.enabled'), 404);

        $availablePaymentMethods = [BillplzPaymentMethod::BILLPLZ];
        if (config('services.billplz.duitnow_qr_enabled')) {
            $availablePaymentMethods[] = BillplzPaymentMethod::DUITNOW_QR;
        }

        $validated = $request->validate([
            'payment_method' => ['nullable', Rule::in($availablePaymentMethods)],
        ]);

        $order = $access->resolveForBalancePayment($request, $orderId);

        return redirect()->away($checkout->create(
            $order,
            $validated['payment_method'] ?? BillplzPaymentMethod::BILLPLZ,
        ));
    }
}
