<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerBookingPaymentController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        CreateBillplzBookingPaymentService $checkout,
    ): RedirectResponse {
        abort_unless(config('services.billplz.enabled'), 404);

        $order = $access->resolve($request, $orderId);
        $result = $checkout->create($order);

        return redirect()->away($result['payment_url']);
    }
}
