<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Orders\ResolveOrderAccessService;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $tokenAccess,
        CustomerOrderSessionAccessService $sessionAccess,
        BuildCustomerProgressService $customerProgress,
    ) {
        $plainToken = (string) $request->query('token', '');

        if ($plainToken !== '') {
            $order = $sessionAccess->establishFromToken($request, $orderId, $plainToken, $tokenAccess);

            return redirect()
                ->route('orders.dashboard', ['orderId' => $order->order_id])
                ->withHeaders([
                    'Cache-Control' => 'no-store',
                    'Referrer-Policy' => 'no-referrer',
                ]);
        }

        $order = $sessionAccess->resolve($request, $orderId);

        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
            'fulfilmentJob',
        ]);

        return view('orders.dashboard', [
            'order' => $order,
            'progress' => $customerProgress->build($order),
        ]);
    }
}
