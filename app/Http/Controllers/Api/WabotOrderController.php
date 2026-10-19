<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
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
            'package_format' => ['nullable', 'string', 'in:SEPARATE,FOLDED'],
            'first_event_side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
        ]);

        $result = $orders->create($validated);
        $payment = $this->bookingPayment($result['order']);
        abort_if($payment === null, 404);

        $response = [
            'order_id' => $result['order']->order_id,
            'booking' => [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_options' => ['DUITNOW_QR', 'ONLINE_BANKING'],
                'preferred_payment_option' => 'DUITNOW_QR',
                'checkout_url' => $payment->status === 'PAID' ? null : $result['payment_url'],
            ],
        ];

        if ($payment->status === 'PAID') {
            $response = array_merge($response, $this->paidOrderDetails($result['order']->fresh(), $payment));
        }

        return response()->json($response, $result['reused'] ? 200 : 201);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = Order::query()->where('source', 'WABOT')->where('order_id', $orderId)->firstOrFail();
        $payment = $this->bookingPayment($order);
        $response = [
            'order_id' => $order->order_id,
            'order_status' => $order->status,
            'booking_payment_status' => $order->booking_payment_status,
            'booking' => $payment ? [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'checkout_url' => $payment->status === 'PENDING'
                    ? ($payment->metadata['billplz']['checkout_url'] ?? null)
                    : null,
            ] : null,
        ];

        if ($payment?->status === 'PAID') {
            $response = array_merge($response, $this->paidOrderDetails($order, $payment));
        }

        return response()->json($response);
    }

    /** @return array{customer_reference: string|null, invoice_number: string|null, dashboard_url: string|null, receipt_url: string} */
    private function paidOrderDetails(Order $order, PaymentTransaction $payment): array
    {
        $encryptedUrl = $payment->metadata['customer_access']['dashboard_url_encrypted'] ?? null;

        return [
            'customer_reference' => $order->customer_reference,
            'invoice_number' => $order->invoice()->value('invoice_number'),
            'dashboard_url' => is_string($encryptedUrl) ? Crypt::decryptString($encryptedUrl) : null,
            'receipt_url' => URL::temporarySignedRoute(
                'payments.receipt.show',
                now()->addDays(7),
                ['orderId' => $order->order_id, 'payment' => $payment->id],
            ),
        ];
    }

    private function bookingPayment(Order $order): ?PaymentTransaction
    {
        return $order->payments()
            ->where('payment_type', 'BOOKING_DEPOSIT')
            ->where('status', 'PAID')
            ->latest('id')
            ->first()
            ?? $order->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->latest('id')
                ->first();
    }
}
