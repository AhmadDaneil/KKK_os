<?php

namespace App\Http\Controllers;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevOrderController extends Controller
{
    public function store(
    Request $request,
    CreateOrderService $createOrder,
    GenerateOrderAccessLinkService $accessLink
) {
    $validated = $request->validate([
        'package_count' => [
            'required',
            'integer',
            'in:1,2',
        ],

        'side' => [
            'nullable',
            'string',
            'in:LELAKI,PEREMPUAN',
            'required_if:package_count,1',
        ],

        'customer_name' => [
            'nullable',
            'string',
            'max:255',
        ],

        'customer_email' => [
            'nullable',
            'email',
            'max:255',
        ],

        'customer_phone' => [
            'nullable',
            'string',
            'max:30',
        ],
    ]);

    $order = $createOrder->create($validated);

    $dashboardUrl = $accessLink->generate($order);

    return response()->json([
        'order' => [
            'order_id' => $order->order_id,
            'package_count' => $order->package_count,
            'status' => $order->status,
        ],
        'dashboard_url' => $dashboardUrl,
    ], 201);
}
}
