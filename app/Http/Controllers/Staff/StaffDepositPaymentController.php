<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffDepositPaymentController extends Controller
{
    public function receipt(PaymentTransaction $payment): StreamedResponse
    {
        abort_unless(in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE', 'ARTWORK_CORRECTION'], true), 404);
        $metadata = $payment->metadata ?? [];
        $path = $metadata['receipt_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $metadata['receipt_original_name'] ?? 'resit-deposit', [
            'Content-Type' => $metadata['receipt_mime_type'] ?? 'application/octet-stream',
        ]);
    }

    public function approve(Request $request, PaymentTransaction $payment, GenerateMergeJobsForOrderService $merge, InitializeDesignJobsForOrderService $design): RedirectResponse
    {
        $this->authorizePaymentReview($request);
        $this->ensureDeposit($payment);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ], [
            'amount.required' => 'Sila masukkan jumlah bayaran pada resit.',
            'amount.min' => 'Jumlah bayaran mestilah sekurang-kurangnya RM0.01.',
            'amount.decimal' => 'Jumlah bayaran hanya boleh mempunyai sehingga dua tempat perpuluhan.',
        ]);

        DB::transaction(function () use ($request, $payment, $merge, $design, $validated) {
            $payment->refresh()->load('order');
            abort_unless($payment->status === 'PENDING' && $payment->order->status === 'DETAILS_CONFIRMED', 422);
            $metadata = $payment->metadata ?? [];
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            unset($metadata['rejection_reason']);
            $payment->update(['amount' => $validated['amount'], 'status' => 'PAID', 'paid_at' => now(), 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'DEPOSIT_APPROVED', 'payload' => ['actor_user_id' => $request->user()->id], 'occurred_at' => now()]);

            $order = $payment->order;
            $order->update(['booking_payment_status' => 'PAID']);
            $merge->generate($order->fresh());
            $design->initialize($order->fresh());
            $order->update(['status' => 'READY_FOR_DESIGN']);
            $order->statusEvents()->create([
                'event_type' => 'DEPOSIT_APPROVED', 'from_status' => 'DETAILS_CONFIRMED', 'to_status' => 'READY_FOR_DESIGN',
                'actor_user_id' => $request->user()->id, 'source' => 'STAFF', 'occurred_at' => now(),
                'metadata' => ['payment_transaction_id' => $payment->id],
            ]);
        });

        return back()->with('status', 'Deposit disahkan. Order kini sedia untuk proses design.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $this->authorizePaymentReview($request);
        $this->ensureDeposit($payment);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $payment, $validated) {
            $payment->refresh()->load('order');
            abort_unless($payment->status === 'PENDING', 422);
            $metadata = $payment->metadata ?? [];
            $metadata['rejection_reason'] = trim($validated['rejection_reason']);
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            $payment->update(['status' => 'FAILED', 'paid_at' => null, 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'DEPOSIT_REJECTED', 'payload' => ['actor_user_id' => $request->user()->id, 'reason' => $metadata['rejection_reason']], 'occurred_at' => now()]);
            $payment->order()->update(['booking_payment_status' => 'REJECTED']);
        });

        return back()->with('status', 'Deposit ditolak dan customer boleh menghantar resit baharu.');
    }

    private function ensureDeposit(PaymentTransaction $payment): void
    {
        abort_unless($payment->payment_type === 'BOOKING_DEPOSIT', 404);
    }

    private function authorizePaymentReview(Request $request): void
    {
        abort_unless($request->user()?->isOperationManagement(), 403);
    }
}
