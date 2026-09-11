<?php

namespace App\Http\Controllers;

use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;

class CustomerOrderConfirmController extends Controller
{
    public function store(Request $request, string $orderId, CustomerOrderSessionAccessService $access, ConfirmOrderDetailsService $confirm)
    {
        $request->validate(['responsibility_acknowledged' => ['accepted']]);
        $order = $access->resolve($request, $orderId);
        $confirm->confirm($order);

        return redirect()->route('orders.dashboard', ['orderId' => $order->order_id])
            ->with('success', 'Maklumat tempahan telah disahkan.');
    }
}
