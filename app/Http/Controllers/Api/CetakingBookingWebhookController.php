<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Orders\CreateWabotDraftOrderService;
use App\Services\Orders\NormalizeCustomerPhoneService;
use App\Services\Orders\WabotBookingConflictException;
use App\Services\Payments\BillplzPaymentException;
use App\Services\Payments\BillplzPaymentMethod;
use App\Services\Wabot\WabotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CetakingBookingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        CreateWabotDraftOrderService $orders,
        NormalizeCustomerPhoneService $phones,
        WabotService $wabot,
    ): JsonResponse {
        $configuredToken = (string) config('services.wabot.cetaking_booking_webhook_token', '');

        if (! preg_match('/\A[a-f0-9]{64}\z/D', $configuredToken) || ! hash_equals($configuredToken, $token)) {
            return response()->json(['success' => false, 'code' => 'UNAUTHORIZED'], 401);
        }

        if (strlen($request->getContent()) > 65536) {
            return response()->json(['success' => false, 'code' => 'PAYLOAD_TOO_LARGE'], 413);
        }

        if (parse_url((string) config('services.billplz.base_url'), PHP_URL_HOST) !== 'www.billplz-sandbox.com') {
            return response()->json(['success' => false, 'code' => 'SANDBOX_ONLY'], 503);
        }

        if (! config('services.billplz.enabled')) {
            return response()->json(['success' => false, 'code' => 'BILLPLZ_DISABLED'], 503);
        }

        $testPhone = trim((string) config('services.wabot.cetaking_booking_test_phone', ''));
        $wabotBaseUrl = trim((string) config('services.wabot.base_url', ''));
        $wabotCredentials = config('services.wabot.channels.cetaking', []);

        if (! preg_match('/\A601\d{8,9}\z/D', $testPhone)
            || parse_url($wabotBaseUrl, PHP_URL_SCHEME) !== 'https'
            || ! is_string(parse_url($wabotBaseUrl, PHP_URL_HOST))
            || blank($wabotCredentials['instance_id'] ?? null)
            || blank($wabotCredentials['access_token'] ?? null)) {
            return response()->json(['success' => false, 'code' => 'SANDBOX_TEST_NOT_CONFIGURED'], 503);
        }

        $validated = $request->validate([
            'contact.number' => ['nullable', 'string', 'max:128'],
            'contact.id' => ['nullable', 'string', 'max:128'],
            'params.customer_name' => ['required', 'string', 'max:255'],
            'params.customer_phone' => ['nullable', 'string', 'max:32'],
            'params.package_count' => ['required', 'integer', 'in:1,2'],
            'params.package_code' => ['required', 'string', 'in:PUTERI_MANIS,JELATA'],
            'params.payment_method' => ['required', 'string', 'in:ONLINE_BANKING,DUITNOW_QR'],
        ]);

        $phone = $this->resolveCustomerPhone($validated, $phones);

        if (! hash_equals($testPhone, $phone)) {
            return response()->json(['success' => false, 'code' => 'TEST_PHONE_ONLY'], 403);
        }

        $packageCode = $validated['params']['package_code'];
        $paymentMethod = $validated['params']['payment_method'] === 'DUITNOW_QR'
            ? BillplzPaymentMethod::DUITNOW_QR
            : BillplzPaymentMethod::BILLPLZ;

        if ($paymentMethod === BillplzPaymentMethod::DUITNOW_QR
            && ! config('services.billplz.duitnow_qr_enabled')) {
            return response()->json(['success' => false, 'code' => 'PAYMENT_METHOD_DISABLED'], 422);
        }

        try {
            $result = $orders->createForConversation([
                'wabot_channel' => 'cetaking',
                'wabot_conversation_id' => $validated['contact']['id'] ?? null,
                'customer_name' => trim($validated['params']['customer_name']),
                'customer_phone' => $phone,
                'package_count' => (int) $validated['params']['package_count'],
                'package_type' => $packageCode,
                'wabot_package_code' => $packageCode,
            ], $paymentMethod);
        } catch (WabotBookingConflictException) {
            return response()->json(['success' => false, 'code' => 'BOOKING_CONFLICT'], 409);
        } catch (BillplzPaymentException) {
            return response()->json(['success' => false, 'code' => 'BILLPLZ_UNAVAILABLE'], 502);
        }

        $paymentUrlHost = parse_url($result['payment_url'], PHP_URL_HOST);
        $paymentUrlScheme = parse_url($result['payment_url'], PHP_URL_SCHEME);

        if ($paymentUrlScheme !== 'https' || $paymentUrlHost !== 'www.billplz-sandbox.com') {
            Log::error('Cetaking sandbox endpoint rejected a non-sandbox payment URL.', [
                'order_id' => $result['order']->order_id,
            ]);

            return response()->json(['success' => false, 'code' => 'NON_SANDBOX_PAYMENT_URL'], 409);
        }

        $delivery = $wabot->sendText(
            'cetaking',
            $phone,
            "Pautan ujian Billplz Sandbox untuk tempahan Cetaking: {$result['payment_url']}\n\n"
                .'Ini pautan ujian sahaja. Jangan buat bayaran.',
        );

        if (! $delivery['success']) {
            Log::warning('Cetaking sandbox payment link could not be delivered through Wabot.', [
                'order_id' => $result['order']->order_id,
                'error_code' => $delivery['error_code'],
                'http_status' => $delivery['http_status'],
                'retryable' => $delivery['retryable'],
            ]);

            return response()->json(['success' => false, 'code' => 'MESSAGE_DELIVERY_FAILED'], 502);
        }

        Log::info('Cetaking sandbox payment link delivered through Wabot.', [
            'order_id' => $result['order']->order_id,
            'reused_existing_booking' => $result['reused'],
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $result['order']->order_id,
            'payment_status' => 'PENDING',
            'message_sent' => true,
            'reused_existing_booking' => $result['reused'],
        ], $result['reused'] ? 200 : 201);
    }

    /** @param array<string, mixed> $validated */
    private function resolveCustomerPhone(array $validated, NormalizeCustomerPhoneService $phones): string
    {
        $phoneCandidates = [
            $validated['contact']['number'] ?? null,
            $validated['params']['customer_phone'] ?? null,
        ];

        foreach ($phoneCandidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                return $phones->normalize($candidate);
            } catch (ValidationException) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'params.customer_phone' => 'Please provide a valid Malaysian mobile number before requesting a payment link.',
        ]);
    }
}
