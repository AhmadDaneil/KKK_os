<?php

namespace App\Http\Controllers;

use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\BuildOrderPricingService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    ) {
        $validated = $request->validate([
            'responsibility_acknowledged' => ['required', 'accepted'],
            'post_confirmation_liability_acknowledged' => ['required', 'accepted'],
        ], [
            'responsibility_acknowledged.accepted' => 'Anda perlu mengesahkan bahawa semua maklumat telah disemak.',
            'post_confirmation_liability_acknowledged.accepted' => 'Anda perlu bersetuju dengan perakuan kesalahan selepas pengesahan.',
        ]);

        $order = $access->resolve($request, $orderId);
        if ($order->booking_payment_status !== 'PAID') {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran booking mesti berjaya sebelum pengesahan akhir.',
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

        return redirect()->route('orders.thank-you.show', ['orderId' => $order->order_id])
            ->with('success', 'Maklumat tempahan telah disahkan.');
    }
}
