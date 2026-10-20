<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Orders\DetectWabotBookingIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WabotBookingIntentController extends Controller
{
    public function store(Request $request, DetectWabotBookingIntentService $intent): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $bookingRequested = $intent->detects($validated['message']);

        return response()->json([
            'booking_requested' => $bookingRequested,
            'next_action' => $bookingRequested ? 'ASK_PAYMENT_METHOD' : null,
            'payment_method_prompt' => $bookingRequested
                ? 'Anda ingin membayar deposit tempahan melalui Perbankan Dalam Talian atau DuitNow QR?'
                : null,
            'payment_options' => $bookingRequested ? ['ONLINE_BANKING', 'DUITNOW_QR'] : [],
        ]);
    }
}
