<?php

use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffJobAssignmentController;
use App\Http\Controllers\Staff\StaffDesignWorkflowController;
use App\Http\Controllers\CustomerArtworkReviewController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\CustomerOrderConfirmController;
use App\Http\Controllers\CustomerOrderDraftController;
use App\Http\Controllers\CustomerOrderReviewController;
use App\Http\Controllers\DevBalancePaymentController;
use App\Http\Controllers\DevDesignJobController;
use App\Http\Controllers\DevFulfilmentJobController;
use App\Http\Controllers\DevMergeJobController;
use App\Http\Controllers\DevOrderController;
use App\Http\Controllers\DevPackingJobController;
use App\Http\Controllers\DevPrintJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Routes
|--------------------------------------------------------------------------
*/

Route::get('/order/{orderId}', [CustomerDashboardController::class, 'show'])
    ->name('orders.dashboard');

Route::post(
    '/order/{orderId}/draft',
    [CustomerOrderDraftController::class, 'update']
)->name('orders.draft.update');

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

Route::get(
    '/order/{orderId}/artwork/{designJobId}/preview',
    [CustomerArtworkReviewController::class, 'preview']
)->name('orders.artwork.preview');

Route::post(
    '/order/{orderId}/artwork/{designJobId}/correction',
    [CustomerArtworkReviewController::class, 'correction']
)->name('orders.artwork.correction');

Route::post(
    '/order/{orderId}/artwork/{designJobId}/approve',
    [CustomerArtworkReviewController::class, 'approve']
)->name('orders.artwork.approve');

/*
|--------------------------------------------------------------------------
| Staff Authentication
|--------------------------------------------------------------------------
*/

Route::get('/staff/login', [StaffAuthController::class, 'create'])
    ->name('staff.login');

Route::post('/staff/login', [StaffAuthController::class, 'store'])
    ->name('staff.login.store');

/*
|--------------------------------------------------------------------------
| Staff Production Routes
|--------------------------------------------------------------------------
|
| All operational staff routes require:
| - authenticated Laravel web session
| - active staff identity
|
| Role-specific actions receive an additional staff.role middleware.
|
*/

Route::middleware(['auth', 'active.staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', [StaffDashboardController::class, 'index'])
            ->name('dashboard');

        Route::post('/logout', [StaffAuthController::class, 'destroy'])
            ->name('logout');

        Route::get('/orders', [StaffOrderController::class, 'index'])
            ->name('orders.index');

        Route::get('/orders/{orderId}', [StaffOrderController::class, 'show'])
            ->name('orders.show');

        /*
        |--------------------------------------------------------------------------
        | Admin Actions
        |--------------------------------------------------------------------------
        |
        | ADMIN may assign/reassign operational jobs.
        |
        */

        Route::middleware('staff.role:ADMIN')->group(function () {
            Route::post(
                '/design-jobs/{designJob}/assign',
                [StaffJobAssignmentController::class, 'assignDesign']
            )->name('design-jobs.assign');

            Route::post(
                '/print-jobs/{printJob}/assign',
                [StaffJobAssignmentController::class, 'assignPrinting']
            )->name('print-jobs.assign');

            Route::post(
                '/packing-jobs/{packingJob}/assign',
                [StaffJobAssignmentController::class, 'assignPacking']
            )->name('packing-jobs.assign');
        });

        /*
        |--------------------------------------------------------------------------
        | Designer Actions
        |--------------------------------------------------------------------------
        |
        | DESIGNER may operate only on design jobs assigned to them.
        | Job ownership is additionally enforced by the production controller.
        |
        */

    Route::middleware('staff.role:DESIGNER')->group(function () {
    Route::post(
        '/design-jobs/{designJob}/start',
        [StaffDesignWorkflowController::class, 'start']
    )->name('design-jobs.start');

    Route::post(
        '/design-jobs/{designJob}/resume-correction',
        [StaffDesignWorkflowController::class, 'resumeCorrection']
    )->name('design-jobs.resume-correction');

    Route::post(
        '/design-jobs/{designJob}/artwork',
        [StaffDesignWorkflowController::class, 'uploadArtwork']
    )->name('design-jobs.artwork.store');
    });
    Route::post(
    '/design-jobs/{designJob}/mark-ready',
    [StaffDesignWorkflowController::class, 'markReady']
    )->name('design-jobs.mark-ready');
});

/*
|--------------------------------------------------------------------------
| Local Development Routes
|--------------------------------------------------------------------------
|
| These endpoints exist only for local development/testing workflows.
| They MUST NOT be registered in testing, staging, or production.
|
*/

if (app()->environment('local')) {
    Route::post('/dev/orders', [DevOrderController::class, 'store'])
        ->name('dev.orders.store');

    Route::post(
        '/dev/orders/{orderId}/merge-jobs',
        [DevMergeJobController::class, 'store']
    )->name('dev.orders.merge-jobs.store');

    Route::post(
        '/dev/orders/{orderId}/design-jobs',
        [DevDesignJobController::class, 'store']
    )->name('dev.orders.design-jobs.store');

    Route::post(
        '/dev/orders/{orderId}/balance-payment',
        [DevBalancePaymentController::class, 'create']
    )->name('dev.orders.balance-payment.create');

    Route::post(
        '/dev/payments/{paymentId}/pay',
        [DevBalancePaymentController::class, 'pay']
    )->name('dev.payments.pay');

    Route::post(
        '/dev/orders/{orderId}/print-jobs',
        [DevPrintJobController::class, 'store']
    )->name('dev.orders.print-jobs.store');

    Route::post(
        '/dev/orders/{orderId}/packing-job',
        [DevPackingJobController::class, 'store']
    )->name('dev.orders.packing-job.store');

    Route::post(
        '/dev/orders/{orderId}/fulfilment-job',
        [DevFulfilmentJobController::class, 'store']
    )->name('dev.orders.fulfilment-job.store');

    Route::post(
        '/dev/fulfilment-jobs/{fulfilmentJobId}/ship',
        [DevFulfilmentJobController::class, 'ship']
    )->name('dev.fulfilment-jobs.ship');

    Route::post(
        '/dev/fulfilment-jobs/{fulfilmentJobId}/deliver',
        [DevFulfilmentJobController::class, 'deliver']
    )->name('dev.fulfilment-jobs.deliver');

    Route::post(
        '/dev/fulfilment-jobs/{fulfilmentJobId}/collect',
        [DevFulfilmentJobController::class, 'collect']
    )->name('dev.fulfilment-jobs.collect');
}