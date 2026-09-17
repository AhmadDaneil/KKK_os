<?php

namespace App\Http\Controllers;

use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;

class CustomerOrderThankYouController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        BuildFinalReviewService $review,
    ) {
        $order = $access->resolve($request, $orderId);

        if ($order->details_confirmed_at === null) {
            return redirect()->route('orders.dashboard', [
                'orderId' => $order->order_id,
            ]);
        }

        return view('orders.thank-you', [
            'order' => $order,
            'review' => $review->build($order),
        ]);
    }
}
