<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\CustomerArtworkReviewController;
use App\Http\Controllers\CustomerBalanceReceiptController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\CustomerDepositReceiptController;
use App\Http\Controllers\CustomerOrderConfirmController;
use App\Http\Controllers\CustomerOrderDraftController;
use App\Http\Controllers\CustomerOrderReviewController;
use App\Http\Controllers\CustomerOrderThankYouController;
use App\Http\Controllers\DevBalancePaymentController;
use App\Http\Controllers\DevDesignJobController;
use App\Http\Controllers\DevFulfilmentJobController;
use App\Http\Controllers\DevMergeJobController;
use App\Http\Controllers\DevOrderController;
use App\Http\Controllers\DevPackingJobController;
use App\Http\Controllers\DevPrintJobController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Staff\StaffBalancePaymentController;
use App\Http\Controllers\Staff\StaffBatchArtworkController;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffDepositPaymentController;
use App\Http\Controllers\Staff\StaffDesignWorkflowController;
use App\Http\Controllers\Staff\StaffJobAssignmentController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffOrderDeletionController;
use App\Http\Controllers\Staff\StaffOrderProductionAssignmentController;
use App\Http\Controllers\Staff\StaffPackingWorkflowController;
use App\Http\Controllers\Staff\StaffPrintingWorkflowController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.landing')->name('home');

Route::get('/tempah', [PublicOrderController::class, 'create'])
    ->name('public.orders.create');

Route::post('/tempah', [PublicOrderController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('public.orders.store');

Route::get('/semak-progress', [PublicOrderController::class, 'progress'])
    ->name('public.orders.progress');

Route::post('/semak-progress', [PublicOrderController::class, 'lookupProgress'])
    ->middleware('throttle:10,1')
    ->name('public.orders.progress.lookup');

Route::post('/order/{orderId}/balance-receipt', [CustomerBalanceReceiptController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('orders.balance-receipt.store');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [AdminAuthController::class, 'create'])
    ->name('admin.login');

Route::post('/admin/login', [AdminAuthController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('admin.login.store');

Route::middleware(['auth:admin', 'active.staff', 'staff.role:ADMIN'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{user}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::put('/staff/{user}/password', [AdminStaffController::class, 'updatePassword'])->name('staff.password.update');
        Route::delete('/staff/{user}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('/orders', [StaffOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderId}', [StaffOrderController::class, 'show'])->name('orders.show');
        Route::delete('/orders/{order}', [StaffOrderDeletionController::class, 'destroy'])
            ->name('orders.destroy');
        Route::post('/orders/{order}/assign-printing', [StaffOrderProductionAssignmentController::class, 'assignPrinting'])
            ->name('orders.assign-printing');
        Route::post('/orders/{order}/assign-packing-fulfilment', [StaffOrderProductionAssignmentController::class, 'assignPackingAndFulfilment'])
            ->name('orders.assign-packing-fulfilment');

        Route::get('/payments/{payment}/receipt', [StaffDepositPaymentController::class, 'receipt'])
            ->name('payments.receipt');
        Route::post('/payments/{payment}/approve-deposit', [StaffDepositPaymentController::class, 'approve'])
            ->name('payments.deposit.approve');
        Route::post('/payments/{payment}/reject-deposit', [StaffDepositPaymentController::class, 'reject'])
            ->name('payments.deposit.reject');
        Route::post('/payments/{payment}/approve-balance', [StaffBalancePaymentController::class, 'approve'])
            ->name('payments.balance.approve');
        Route::post('/payments/{payment}/reject-balance', [StaffBalancePaymentController::class, 'reject'])
            ->name('payments.balance.reject');

        Route::post('/design-jobs/{designJob}/assign', [StaffJobAssignmentController::class, 'assignDesign'])
            ->name('design-jobs.assign');
        Route::post('/design-jobs/{designJob}/start', [StaffDesignWorkflowController::class, 'start'])
            ->name('design-jobs.start');
        Route::post('/design-jobs/{designJob}/resume-correction', [StaffDesignWorkflowController::class, 'resumeCorrection'])
            ->name('design-jobs.resume-correction');
        Route::post('/design-jobs/{designJob}/artwork', [StaffDesignWorkflowController::class, 'uploadArtwork'])
            ->name('design-jobs.artwork.store');
        Route::post('/design-jobs/{designJob}/mark-ready', [StaffDesignWorkflowController::class, 'markReady'])
            ->name('design-jobs.mark-ready');

        Route::post('/print-jobs/{printJob}/assign', [StaffJobAssignmentController::class, 'assignPrinting'])
            ->name('print-jobs.assign');
        Route::post('/print-jobs/{printJob}/start', [StaffPrintingWorkflowController::class, 'start'])
            ->name('print-jobs.start');
        Route::post('/print-jobs/{printJob}/mark-printed', [StaffPrintingWorkflowController::class, 'markPrinted'])
            ->name('print-jobs.mark-printed');

        Route::post('/packing-jobs/{packingJob}/assign', [StaffJobAssignmentController::class, 'assignPacking'])
            ->name('packing-jobs.assign');
        Route::post('/packing-jobs/{packingJob}/start', [StaffPackingWorkflowController::class, 'start'])
            ->name('packing-jobs.start');
        Route::post('/packing-jobs/{packingJob}/items/{packingItem}/verify', [StaffPackingWorkflowController::class, 'verifyItem'])
            ->name('packing-jobs.items.verify');
        Route::post('/packing-jobs/{packingJob}/mark-packed', [StaffPackingWorkflowController::class, 'markPacked'])
            ->name('packing-jobs.mark-packed');
        Route::post('/packing-jobs/{packingJob}/complete-courier', [StaffPackingWorkflowController::class, 'completeCourier'])
            ->name('packing-jobs.complete-courier');
        Route::post('/packing-jobs/{packingJob}/collect-pickup', [StaffPackingWorkflowController::class, 'collectPickup'])
            ->name('packing-jobs.collect-pickup');
    });

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

Route::post('/order/{orderId}/deposit-receipt', [CustomerDepositReceiptController::class, 'update'])
    ->name('orders.deposit-receipt.update');

Route::get(
    '/order/{orderId}/review',
    [CustomerOrderReviewController::class, 'show']
)->name('orders.review.show');

Route::post(
    '/order/{orderId}/confirm',
    [CustomerOrderConfirmController::class, 'store']
)->name('orders.confirm.store');

Route::get(
    '/order/{orderId}/thank-you',
    [CustomerOrderThankYouController::class, 'show']
)->name('orders.thank-you.show');

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
| Staff Production Routes
|--------------------------------------------------------------------------
|
| Staff routes require an authenticated, active staff identity so every
| operational action remains attributable to the staff member who performed it.
|
| Role-specific actions receive an additional staff.role middleware.
|
*/

Route::get('/staff/login', [StaffAuthController::class, 'create'])
    ->name('staff.login');

Route::post('/staff/login', [StaffAuthController::class, 'store'])
    ->name('staff.login.store');

Route::middleware(['auth:staff', 'active.staff'])
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

        Route::middleware('staff.role:ADMIN,'.User::ROLE_OM)->group(function () {
            Route::delete('/orders/{order}', [StaffOrderDeletionController::class, 'destroy'])
                ->name('orders.destroy');
            Route::post('/orders/{order}/assign-printing', [StaffOrderProductionAssignmentController::class, 'assignPrinting'])
                ->name('orders.assign-printing');
            Route::post('/orders/{order}/assign-packing-fulfilment', [StaffOrderProductionAssignmentController::class, 'assignPackingAndFulfilment'])
                ->name('orders.assign-packing-fulfilment');
            Route::post(
                '/design-jobs/{designJob}/assign',
                [StaffJobAssignmentController::class, 'assignDesign']
            )->name('design-jobs.assign');
            Route::post(
                '/print-jobs/{printJob}/assign',
                [StaffJobAssignmentController::class, 'assignPrinting']
            )->name('print-jobs.assign');
            Route::get('/payments/{payment}/receipt', [StaffDepositPaymentController::class, 'receipt'])
                ->name('payments.receipt');
            Route::post('/payments/{payment}/approve-deposit', [StaffDepositPaymentController::class, 'approve'])
                ->name('payments.deposit.approve');
            Route::post('/payments/{payment}/reject-deposit', [StaffDepositPaymentController::class, 'reject'])
                ->name('payments.deposit.reject');
            Route::post('/payments/{payment}/approve-balance', [StaffBalancePaymentController::class, 'approve'])
                ->name('payments.balance.approve');
            Route::post('/payments/{payment}/reject-balance', [StaffBalancePaymentController::class, 'reject'])
                ->name('payments.balance.reject');
        });

        Route::middleware('staff.role:ADMIN')->group(function () {
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

            Route::post(
                '/orders/{order}/design-artworks',
                [StaffBatchArtworkController::class, 'store']
            )->name('orders.design-artworks.store');

            Route::post(
                '/design-jobs/{designJob}/mark-ready',
                [StaffDesignWorkflowController::class, 'markReady']
            )->name('design-jobs.mark-ready');
        });
        /*
        |--------------------------------------------------------------------------
        | Printing Actions
        |--------------------------------------------------------------------------
        |
        | PRINTING staff may operate only on print jobs assigned to them.
        | Job ownership is additionally enforced by the production controller.
        |
        */

        Route::middleware('staff.role:PRINTING')->group(function () {
            Route::post(
                '/print-jobs/{printJob}/start',
                [StaffPrintingWorkflowController::class, 'start']
            )->name('print-jobs.start');

            Route::post(
                '/print-jobs/{printJob}/mark-printed',
                [StaffPrintingWorkflowController::class, 'markPrinted']
            )->name('print-jobs.mark-printed');
        });

        /*
            |--------------------------------------------------------------------------
            | Packing Actions
            |--------------------------------------------------------------------------
            |
            | OM may operate every packing job because packing is handled by OM.
            | PACKING staff remain limited to jobs assigned to them.
            |
            */

        Route::middleware('staff.role:PACKING,OPERATION_MANAGEMENT')->group(function () {
            Route::post(
                '/packing-jobs/{packingJob}/start',
                [StaffPackingWorkflowController::class, 'start']
            )->name('packing-jobs.start');

            Route::post(
                '/packing-jobs/{packingJob}/items/{packingItem}/verify',
                [StaffPackingWorkflowController::class, 'verifyItem']
            )->name('packing-jobs.items.verify');

            Route::post(
                '/packing-jobs/{packingJob}/mark-packed',
                [StaffPackingWorkflowController::class, 'markPacked']
            )->name('packing-jobs.mark-packed');

            Route::post(
                '/packing-jobs/{packingJob}/complete-courier',
                [StaffPackingWorkflowController::class, 'completeCourier']
            )->name('packing-jobs.complete-courier');

            Route::post(
                '/packing-jobs/{packingJob}/collect-pickup',
                [StaffPackingWorkflowController::class, 'collectPickup']
            )->name('packing-jobs.collect-pickup');

        });

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
        '/dev/fulfilment-jobs/{fulfilmentJobId}/collect',
        [DevFulfilmentJobController::class, 'collect']
    )->name('dev.fulfilment-jobs.collect');
}
