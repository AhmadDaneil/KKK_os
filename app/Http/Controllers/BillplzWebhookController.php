<?php

namespace App\Http\Controllers;

use App\Services\Payments\BillplzSignatureVerifier;
use App\Services\Payments\HandlePaymentCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class BillplzWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        BillplzSignatureVerifier $verifier,
        HandlePaymentCallbackService $handler,
    ): JsonResponse {
        try {
            $verified = $verifier->verifyCallback($request->all());
            $payment = $handler->handle($verified);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'The Billplz callback is invalid or does not match a payment record.',
                'code' => 'INVALID_BILLPLZ_CALLBACK',
            ], 400);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The callback cannot be processed right now.',
                'code' => 'CALLBACK_PROCESSING_FAILED',
            ], 500);
        }

        return response()->json([
            'received' => true,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
        ]);
    }
}
