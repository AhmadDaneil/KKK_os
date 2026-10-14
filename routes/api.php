<?php

use App\Http\Controllers\Api\WabotOrderController;
use App\Http\Controllers\BillplzWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/payments/billplz-sandbox/callback', BillplzWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.billplz-sandbox.callback');

Route::middleware(['wabot.auth', 'throttle:60,1'])->prefix('/v1/wabot')->group(function (): void {
    Route::post('/orders', [WabotOrderController::class, 'store'])->name('api.wabot.orders.store');
    Route::get('/orders/{orderId}', [WabotOrderController::class, 'show'])->name('api.wabot.orders.show');
});
