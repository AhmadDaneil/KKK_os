<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffBalancePaymentController extends Controller
{
    public function approve(Request $request, PaymentTransaction $payment, InitializePrintJobsForOrderService $initializePrint, SyncOrderPrintStatusService $syncPrint, SynchronizeOrderFinancialsService $financials): RedirectResponse
    {
        $this->authorizePaymentReview($request);
        $this->ensureBalance($payment);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ], [
            'amount.required' => 'Please enter the payment amount shown on the receipt.',
            'amount.min' => 'The payment amount must be at least RM0.01.',
            'amount.decimal' => 'The payment amount may have up to two decimal places.',
        ]);

        DB::transaction(function () use ($request, $payment, $initializePrint, $syncPrint, $financials, $validated) {
            $payment->refresh()->load('order');
            abort_unless($payment->status === 'PENDING' && $payment->order->status === 'BALANCE_PENDING', 422);
            $metadata = $payment->metadata ?? [];
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            unset($metadata['rejection_reason']);
            $payment->update(['amount' => $validated['amount'], 'status' => 'PAID', 'paid_at' => now(), 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'BALANCE_APPROVED', 'payload' => ['actor_user_id' => $request->user()->id], 'occurred_at' => now()]);

            $order = $payment->order;
            $financials->synchronize($order);
            $order->update(['status' => 'PAID']);
            $initializePrint->initialize($order->fresh());
            $syncPrint->sync($order->fresh());
        });

        return back()->with('status', 'Full payment confirmed. A print job has been created.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $this->authorizePaymentReview($request);
        $this->ensureBalance($payment);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $payment, $validated) {
            $payment->refresh()->load('order');
            abort_unless($payment->status === 'PENDING', 422);
            $metadata = $payment->metadata ?? [];
            $metadata['rejection_reason'] = trim($validated['rejection_reason']);
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            $payment->update(['status' => 'FAILED', 'paid_at' => null, 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'BALANCE_REJECTED', 'payload' => ['actor_user_id' => $request->user()->id, 'reason' => $metadata['rejection_reason']], 'occurred_at' => now()]);
            $payment->order->update(['status' => 'DESIGN_APPROVED']);
        });

        return back()->with('status', 'The full payment receipt was rejected. The customer may submit a new receipt.');
    }

    private function ensureBalance(PaymentTransaction $payment): void
    {
        abort_unless($payment->payment_type === 'BALANCE', 404);
    }

    private function authorizePaymentReview(Request $request): void
    {
        abort_unless($request->user()?->isOperationManagement(), 403);
    }
}
