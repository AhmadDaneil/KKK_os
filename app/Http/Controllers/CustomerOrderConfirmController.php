<?php

namespace App\Http\Controllers;

use App\Services\Orders\AdvancePaidConfirmedOrderToDesignService;
use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerOrderConfirmController extends Controller
{
    public function store(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        ConfirmOrderDetailsService $confirm,
        BuildOrderPricingService $pricing,
        BuildFinalReviewService $review,
        SynchronizeOrderFinancialsService $financials,
        AdvancePaidConfirmedOrderToDesignService $designReadiness,
    ) {
        $validated = $request->validate([
            'responsibility_acknowledged' => ['required', 'accepted'],
            'post_confirmation_liability_acknowledged' => ['required', 'accepted'],
        ], [
            'responsibility_acknowledged.accepted' => 'You must confirm that you have reviewed all details.',
            'post_confirmation_liability_acknowledged.accepted' => 'You must agree to the post-confirmation liability statement.',
        ]);

        $order = $access->resolve($request, $orderId);
        if ($order->booking_payment_status !== 'PAID') {
            throw ValidationException::withMessages([
                'payment' => 'The booking payment must succeed before final confirmation.',
            ]);
        }

        $livePreview = $review->build($order);
        $order = DB::transaction(function () use ($order, $confirm, $pricing, $financials, $livePreview) {
            $confirmedOrder = $confirm->confirm($order, $livePreview);
            $pricing->createSnapshot($confirmedOrder);
            $confirmation = $confirmedOrder->confirmation;
            $confirmation->update([
                'confirmed_snapshot' => array_merge($confirmation->confirmed_snapshot ?? [], [
                    'responsibility_acknowledged' => true,
                    'post_confirmation_liability_acknowledged' => true,
                ]),
            ]);
            $financials->synchronize($confirmedOrder);

            return $confirmedOrder->fresh();
        });

        try {
            $order = $designReadiness->advance(
                $order,
                eventType: 'DETAILS_CONFIRMED_AFTER_PAYMENT',
            );
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Confirmed paid order could not be advanced to the design queue.', [
                'order_id' => $order->id,
            ]);
        }

        return redirect()->route('orders.thank-you.show', ['orderId' => $order->order_id])
            ->with('success', 'The order details have been confirmed.');
    }
}
