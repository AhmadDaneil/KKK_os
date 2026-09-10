# KKK OS V1 Stage 2D - Completion Validation + Final Review + Confirm

## Add these routes to routes/web.php

use App\Http\Controllers\CustomerOrderReviewController;
use App\Http\Controllers\CustomerOrderConfirmController;

Route::get('/order/{orderId}/review', [CustomerOrderReviewController::class, 'show'])
    ->name('orders.review.show');

Route::post('/order/{orderId}/confirm', [CustomerOrderConfirmController::class, 'store'])
    ->name('orders.confirm.store');

## Dashboard button
Add a link/button from the dashboard to:
route('orders.review.show', ['orderId' => $order->order_id, 'token' => $token])

## Important
- Do not exempt review/confirm routes from CSRF.
- Save Draft remains DETAILS_INCOMPLETE.
- Confirm moves order to DETAILS_CONFIRMED.
- Confirmation writes an order_confirmations row.
- 1 package validates only its actual package side.
- 2 packages validate both LELAKI and PEREMPUAN.
- Courier requires recipient name, phone and shipping address.
