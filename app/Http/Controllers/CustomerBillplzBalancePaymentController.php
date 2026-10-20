<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\CreateBillplzBalancePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerBillplzBalancePaymentController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        CreateBillplzBalancePaymentService $checkout,
    ): RedirectResponse {
        abort_unless(config('services.billplz.enabled'), 404);

        $order = $access->resolveForBalancePayment($request, $orderId);

        return redirect()->away($checkout->create($order));
    }
}
