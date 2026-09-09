<?php

use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\DevOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/order/{orderId}', [CustomerDashboardController::class, 'show'])
    ->name('orders.dashboard');

Route::post('/dev/orders', [DevOrderController::class, 'store'])
    ->name('dev.orders.store');
