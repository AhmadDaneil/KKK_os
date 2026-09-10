<?php

use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\DevOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerOrderDraftController;
use App\Http\Controllers\CustomerOrderReviewController;
use App\Http\Controllers\CustomerOrderConfirmController;
use App\Http\Controllers\DevMergeJobController;
use App\Http\Controllers\DevDesignJobController;
use App\Http\Controllers\CustomerArtworkReviewController;
use App\Http\Controllers\DevPrintJobController;

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

Route::get(
    '/order/{orderId}/artwork',
    [CustomerArtworkReviewController::class, 'show']
)->name('orders.artwork.review');

Route::post(
    '/order/{orderId}/artwork/{designJobId}/correction',
    [CustomerArtworkReviewController::class, 'correction']
)->name('orders.artwork.correction');

Route::post(
    '/order/{orderId}/artwork/{designJobId}/approve',
    [CustomerArtworkReviewController::class, 'approve']
)->name('orders.artwork.approve');

if (app()->environment('local')) {
    Route::post(
        '/dev/orders/{orderId}/merge-jobs',
        [DevMergeJobController::class, 'store']
    )->name('dev.orders.merge-jobs.store');
}

if (app()->environment('local')) {
    Route::post(
        '/dev/orders/{orderId}/design-jobs',
        [DevDesignJobController::class, 'store']
    )->name('dev.orders.design-jobs.store');
}

if (app()->environment('local')) {
    Route::post(
        '/dev/orders/{orderId}/balance-payment',
        [DevBalancePaymentController::class, 'create']
    )->name('dev.orders.balance-payment.create');

    Route::post(
        '/dev/payments/{paymentId}/pay',
        [DevBalancePaymentController::class, 'pay']
    )->name('dev.payments.pay');
}

if (app()->environment('local')) {
    Route::post(
        '/dev/orders/{orderId}/print-jobs',
        [DevPrintJobController::class, 'store']
    )->name('dev.orders.print-jobs.store');
}
