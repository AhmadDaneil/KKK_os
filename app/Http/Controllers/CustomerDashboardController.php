<?php

namespace App\Http\Controllers;

use App\Services\Orders\ResolveOrderAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $access,
    ): View {
        $plainToken = (string) $request->query('token', '');
        $order = $access->resolve($orderId, $plainToken);

        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
        ]);

        return view('orders.dashboard', compact('order', 'plainToken'));
    }
}
