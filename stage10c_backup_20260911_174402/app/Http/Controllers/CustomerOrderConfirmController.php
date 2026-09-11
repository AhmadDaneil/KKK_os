<?php

namespace App\Http\Controllers;

use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\ResolveOrderAccessService;
use Illuminate\Http\Request;

class CustomerOrderConfirmController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $access,
        ConfirmOrderDetailsService $confirm,
    ) {
        $request->validate([
            'responsibility_acknowledged' => ['accepted'],
        ]);

        $order = $access->resolve($orderId, $request->input('token'));

        $confirm->confirm($order);

        return redirect()
            ->route('orders.dashboard', [
                'orderId' => $order->order_id,
                'token' => $request->input('token'),
            ])
            ->with('success', 'Maklumat tempahan telah disahkan.');
    }
}
