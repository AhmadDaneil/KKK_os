<?php

use App\Http\Controllers\Api\WabotBalancePaymentController;
use App\Http\Controllers\Api\WabotBookingIntentController;
use App\Http\Controllers\Api\WabotOrderController;
use App\Http\Controllers\Api\RoyaliciousAutomationProbeController;
use App\Http\Controllers\BillplzWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/payments/billplz-sandbox/callback', BillplzWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.billplz-sandbox.callback');
Route::post('/v1/payments/billplz/callback', BillplzWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.billplz.callback');
Route::post('/v1/booking-intents', [WabotOrderController::class, 'store'])
    ->middleware(['booking-intent.auth', 'throttle:60,1'])
    ->name('api.booking-intents.store');

Route::post('/v1/integrations/wabot/royalicious/automation-probe/{token}', RoyaliciousAutomationProbeController::class)
    ->middleware('throttle:20,1')
    ->name('api.wabot.royalicious.automation-probe');

Route::middleware(['wabot.auth', 'throttle:60,1'])->prefix('/v1/wabot')->group(function (): void {
    Route::post('/booking-intent', [WabotBookingIntentController::class, 'store'])
        ->name('api.wabot.booking-intent.store');
    Route::post('/orders', [WabotOrderController::class, 'store'])->name('api.wabot.orders.store');
    Route::get('/orders/{orderId}', [WabotOrderController::class, 'show'])->name('api.wabot.orders.show');
    Route::post('/orders/{orderId}/balance-payment', [WabotBalancePaymentController::class, 'store'])
        ->name('api.wabot.orders.balance-payment.store');
});
