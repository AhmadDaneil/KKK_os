<?php

use App\Http\Controllers\BillplzWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/payments/billplz-sandbox/callback', BillplzWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.billplz-sandbox.callback');
