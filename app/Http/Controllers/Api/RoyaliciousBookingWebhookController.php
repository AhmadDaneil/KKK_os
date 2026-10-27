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

class RoyaliciousBookingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        CreateWabotDraftOrderService $orders,
        NormalizeCustomerPhoneService $phones,
        WabotService $wabot,
    ): JsonResponse {
        $configuredToken = (string) config('services.wabot.royalicious_booking_webhook_token', '');

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

        $testPhone = trim((string) config('services.wabot.royalicious_booking_test_phone', ''));
        $wabotBaseUrl = trim((string) config('services.wabot.base_url', ''));
        $wabotCredentials = config('services.wabot.channels.royalicious', []);

        if (! preg_match('/\A601\d{8,9}\z/D', $testPhone)
            || parse_url($wabotBaseUrl, PHP_URL_SCHEME) !== 'https'
            || ! is_string(parse_url($wabotBaseUrl, PHP_URL_HOST))
            || blank($wabotCredentials['instance_id'] ?? null)
            || blank($wabotCredentials['access_token'] ?? null)) {
            return response()->json(['success' => false, 'code' => 'SANDBOX_TEST_NOT_CONFIGURED'], 503);
        }

        $validated = $request->validate([
            'contact.number' => ['required', 'string', 'max:32'],
            'params.customer_name' => ['required', 'string', 'max:255'],
            'params.package_count' => ['required', 'integer', 'in:1,2'],
            'params.package_code' => ['required', 'string', 'in:PUTERI_MANIS,JELATA'],
            'params.payment_method' => ['required', 'string', 'in:ONLINE_BANKING,DUITNOW_QR'],
        ]);

        $phone = $phones->normalize($validated['contact']['number']);

        if (! hash_equals($testPhone, $phone)) {
            return response()->json(['success' => false, 'code' => 'TEST_PHONE_ONLY'], 403);
        }

        $packageCode = $validated['params']['package_code'];
        $billplzPaymentMethod = $validated['params']['payment_method'] === 'DUITNOW_QR'
            ? BillplzPaymentMethod::DUITNOW_QR
            : BillplzPaymentMethod::BILLPLZ;

        if ($billplzPaymentMethod === BillplzPaymentMethod::DUITNOW_QR
            && ! config('services.billplz.duitnow_qr_enabled')) {
            return response()->json(['success' => false, 'code' => 'PAYMENT_METHOD_DISABLED'], 422);
        }

        try {
            $result = $orders->createForConversation([
                'wabot_channel' => 'royalicious',
                'wabot_conversation_id' => null,
                'customer_name' => trim($validated['params']['customer_name']),
                'customer_phone' => $phone,
                'package_count' => (int) $validated['params']['package_count'],
                'package_type' => $packageCode,
                'wabot_package_code' => $packageCode,
            ], $billplzPaymentMethod);
        } catch (WabotBookingConflictException) {
            return response()->json(['success' => false, 'code' => 'BOOKING_CONFLICT'], 409);
        } catch (BillplzPaymentException) {
            return response()->json(['success' => false, 'code' => 'BILLPLZ_UNAVAILABLE'], 502);
        }

        $paymentUrlHost = parse_url($result['payment_url'], PHP_URL_HOST);
        $paymentUrlScheme = parse_url($result['payment_url'], PHP_URL_SCHEME);

        if ($paymentUrlScheme !== 'https' || $paymentUrlHost !== 'www.billplz-sandbox.com') {
            Log::error('Royalicious sandbox endpoint rejected a non-sandbox payment URL.', [
                'order_id' => $result['order']->order_id,
            ]);

            return response()->json(['success' => false, 'code' => 'NON_SANDBOX_PAYMENT_URL'], 409);
        }

        $delivery = $wabot->sendText(
            'royalicious',
            $phone,
            "Pautan ujian Billplz Sandbox untuk tempahan Royalicious: {$result['payment_url']}\n\n"
                .'Ini pautan ujian sahaja. Jangan buat bayaran.',
        );

        if (! $delivery['success']) {
            Log::warning('Royalicious sandbox payment link could not be delivered through Wabot.', [
                'order_id' => $result['order']->order_id,
                'error_code' => $delivery['error_code'],
                'http_status' => $delivery['http_status'],
                'retryable' => $delivery['retryable'],
            ]);

            return response()->json(['success' => false, 'code' => 'MESSAGE_DELIVERY_FAILED'], 502);
        }

        Log::info('Royalicious sandbox payment link delivered through Wabot.', [
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
}
