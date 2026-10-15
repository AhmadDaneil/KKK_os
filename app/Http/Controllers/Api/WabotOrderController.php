<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\CreateWabotDraftOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class WabotOrderController extends Controller
{
    public function store(Request $request, CreateWabotDraftOrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:128'],
            'package_count' => ['required', 'integer', 'in:1,2'],
            'side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,1'],
            'package_type' => ['required', 'string', 'in:PUTERI_MANIS,JELATA'],
            'package_format' => ['nullable', 'string', 'in:SEPARATE,FOLDED', 'required_if:package_count,2'],
            'first_event_side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,2'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
        ]);

        $result = $orders->create($validated);
        $payment = $result['order']->payments()->where('payment_type', 'BOOKING_DEPOSIT')->latest('id')->firstOrFail();

        return response()->json([
            'order_id' => $result['order']->order_id,
            'booking' => [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_options' => ['DUITNOW_QR', 'ONLINE_BANKING'],
                'preferred_payment_option' => 'DUITNOW_QR',
                'checkout_url' => $result['payment_url'],
            ],
        ], $result['reused'] ? 200 : 201);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = Order::query()->where('order_id', $orderId)->firstOrFail();
        $payment = $order->payments()->where('payment_type', 'BOOKING_DEPOSIT')->latest('id')->first();
        $response = [
            'order_id' => $order->order_id,
            'order_status' => $order->status,
            'booking_payment_status' => $order->booking_payment_status,
        ];

        if ($payment?->status === 'PAID') {
            $encryptedUrl = $payment->metadata['customer_access']['dashboard_url_encrypted'] ?? null;
            $response['customer_reference'] = $order->customer_reference;
            $response['dashboard_url'] = is_string($encryptedUrl) ? Crypt::decryptString($encryptedUrl) : null;
            $response['receipt_url'] = URL::temporarySignedRoute(
                'payments.receipt.show',
                now()->addDays(7),
                ['orderId' => $order->order_id, 'payment' => $payment->id],
            );
        }

        return response()->json($response);
    }
}
