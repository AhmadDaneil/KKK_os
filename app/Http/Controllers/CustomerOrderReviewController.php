<?php

namespace App\Http\Controllers;

use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\ValidateOrderCompletionService;
use Illuminate\Http\Request;

class CustomerOrderReviewController extends Controller
{
    public function show(Request $request, string $orderId, CustomerOrderSessionAccessService $access, ValidateOrderCompletionService $validator, BuildFinalReviewService $review)
    {
        $order = $access->resolve($request, $orderId);
        $validation = $validator->validate($order);

        if (! $validation['complete']) {
            return redirect()->route('orders.dashboard', ['orderId' => $order->order_id])
                ->withErrors(['completion' => $validation['missing']]);
        }

        return view('orders.final-review', [
            'order' => $order,
            'review' => $review->build($order),
        ]);
    }
}
