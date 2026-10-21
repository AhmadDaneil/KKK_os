<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Orders\CreateWabotDraftOrderService;
use App\Services\Orders\WabotBookingConflictException;
use App\Services\Payments\BillplzPaymentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class WabotOrderController extends Controller
{
    public function store(Request $request, CreateWabotDraftOrderService $orders): JsonResponse
    {
        $canonical = $request->routeIs('api.booking-intents.store');
        if ($canonical && ! $request->isJson()) {
            $rawPayload = json_decode($request->getContent(), true);
            if (is_array($rawPayload)) {
                $request->merge($rawPayload);
            }
        }

        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        $rules = [
            'external_reference' => ['required', 'string', 'max:128'],
            'idempotency_key' => ['nullable', 'string', 'max:160', 'regex:/^booking-[A-Za-z0-9._:-]{1,128}$/'],
            'brand' => ['nullable', 'string', 'in:kingkadkahwin,destinasi-raja-sehari,cetaking,royalicious'],
            'package_count' => ['required', 'integer', 'in:1,2'],
            'side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,1'],
            'package_type' => ['required', 'string', 'in:PUTERI_MANIS,JELATA'],
            'package_format' => ['nullable', 'string', 'in:SEPARATE,FOLDED'],
            'first_event_side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
        ];

        if ($canonical) {
            $rules['external_reference'] = ['nullable', 'string', 'max:128'];
            $rules['conversation_id'] = ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'];
            $rules['idempotency_key'] = ['nullable', 'string', 'max:160'];
            $rules['business_unit'] = ['required', 'string', 'in:KKK,DRS,CK,RYC'];
            $rules['phone'] = ['required', 'string', 'max:32'];
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['package_code'] = ['nullable', 'string', 'max:64'];
            $rules['package_type'][0] = 'nullable';
            $rules['side'] = ['nullable', 'string', 'in:LELAKI,PEREMPUAN'];
            $rules['customer_phone'][0] = 'nullable';
        }

        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $exception) {
            Log::warning('Rejected invalid Wabot booking request.', [
                'fields' => array_keys($exception->errors()),
            ]);

            throw $exception;
        }

        if ($canonical) {
            $channels = ['KKK' => 'kingkadkahwin', 'DRS' => 'destinasi-raja-sehari', 'CK' => 'cetaking', 'RYC' => 'royalicious'];
            $validated['wabot_conversation_id'] = $validated['conversation_id'];
            $validated['brand'] = $channels[$validated['business_unit']];
            $validated['customer_phone'] = $validated['phone'];
            $validated['customer_email'] = $validated['email'] ?? null;
            $validated['wabot_package_code'] = $validated['package_code'] ?? null;
            unset($validated['external_reference'], $validated['conversation_id'], $validated['business_unit'], $validated['phone'], $validated['email'], $validated['package_code']);
        }

        $validated['wabot_idempotency_key'] = $canonical ? null : ($validated['idempotency_key'] ?? null);
        $validated['wabot_channel'] = $validated['brand'] ?? 'kingkadkahwin';
        unset($validated['idempotency_key'], $validated['brand']);

        try {
            $result = $canonical ? $orders->createForConversation($validated) : $orders->create($validated);
        } catch (WabotBookingConflictException $exception) {
            Log::warning('Wabot booking request conflicted with an existing order.', [
                'external_reference' => $validated['external_reference'] ?? null,
                'conversation_id' => $validated['wabot_conversation_id'] ?? null,
            ]);

            return response()->json(['message' => $exception->getMessage(), 'code' => 'BOOKING_CONFLICT'], 409);
        } catch (BillplzPaymentException $exception) {
            return response()->json([
                'message' => 'Billplz is unavailable. Please retry later or contact support.',
                'code' => 'BILLPLZ_UNAVAILABLE',
            ], 502);
        }
        $payment = $this->bookingPayment($result['order']);
        abort_if($payment === null, 404);

        if ($canonical) {
            $response = [
                'success' => true,
                'draft_order_id' => $result['order']->order_id,
                'booking_amount' => (int) round(((float) $payment->amount) * 100),
                'currency' => $payment->currency,
                'payment_status' => $payment->status,
                'payment_url' => $payment->status === 'PAID' ? null : $result['payment_url'],
                'provider_bill_id' => $payment->provider_reference,
                'reused_existing_booking' => $result['reused'],
            ];

            if ($payment->status === 'PAID') {
                $response = array_merge($response, $this->paidOrderDetails($result['order']->fresh(), $payment));
            }

            return response()->json($response, $result['reused'] ? 200 : 201);
        }

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
            $response['balance'] = $this->balancePaymentDetails($order);
        }

        return response()->json($response);
    }

    /** @return array{order_reference: string|null, payment_status: string, customer_reference: string|null, invoice_number: string|null, dashboard_url: string|null, receipt_url: string} */
    private function paidOrderDetails(Order $order, PaymentTransaction $payment): array
    {
        $encryptedUrl = $payment->metadata['customer_access']['dashboard_url_encrypted'] ?? null;

        return [
            'order_reference' => $order->customer_reference,
            'payment_status' => 'PAID',
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

    /** @return array{amount: string, currency: string, status: string, checkout_url: string|null, payment_options: array<int, string>}|null */
    private function balancePaymentDetails(Order $order): ?array
    {
        $payment = $order->payments()
            ->where('payment_type', 'BALANCE')
            ->where('status', 'PAID')
            ->latest('id')
            ->first()
            ?? $order->payments()
                ->where('payment_type', 'BALANCE')
                ->latest('id')
                ->first();

        if ($payment === null && $order->status !== 'DESIGN_APPROVED') {
            return null;
        }

        $snapshot = $order->pricingSnapshot()->first();

        return [
            'amount' => $payment?->status === 'PAID'
                ? $payment->amount
                : (string) ($snapshot?->outstanding_amount ?? $payment?->amount ?? '0.00'),
            'currency' => $payment?->currency ?? 'MYR',
            'status' => $payment?->status ?? 'DUE',
            'checkout_url' => $payment?->status === 'PENDING' && $payment->provider === 'BILLPLZ'
                ? ($payment->metadata['billplz']['checkout_url'] ?? null)
                : null,
            'payment_options' => ['ONLINE_BANKING', 'DUITNOW_QR'],
        ];
    }
}
