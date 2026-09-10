<?php

use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\DevOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerOrderDraftController;
use App\Http\Controllers\CustomerOrderReviewController;
use App\Http\Controllers\CustomerOrderConfirmController;
use App\Http\Controllers\DevMergeJobController;

Route::get('/order/{orderId}', [CustomerDashboardController::class, 'show'])
    ->name('orders.dashboard');

Route::post(
    '/order/{orderId}/draft',
    [CustomerOrderDraftController::class, 'update']
)->name('orders.draft.update');

Route::post('/dev/orders', [DevOrderController::class, 'store'])
    ->name('dev.orders.store');

Route::get(
    '/order/{orderId}/review',
    [CustomerOrderReviewController::class, 'show']
)->name('orders.review.show');

Route::post(
    '/order/{orderId}/confirm',
    [CustomerOrderConfirmController::class, 'store']
)->name('orders.confirm.store');

if (app()->environment('local')) {
    Route::post(
        '/dev/orders/{orderId}/merge-jobs',
        [DevMergeJobController::class, 'store']
    )->name('dev.orders.merge-jobs.store');
}
