<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerDepositReceiptController extends Controller
{
    public function update(Request $request, string $orderId, CustomerOrderSessionAccessService $access): RedirectResponse
    {
        $validated = $request->validate(['deposit_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]);
        $order = $access->resolve($request, $orderId);
        $payment = $order->payments()->where('payment_type', 'BOOKING_DEPOSIT')->firstOrFail();
        abort_unless($payment->status === 'FAILED', 422);

        $receipt = $validated['deposit_receipt'];
        $path = $receipt->store("deposit-receipts/{$order->id}", 'local');
        $metadata = $payment->metadata ?? [];
        $metadata['receipt_history'][] = ['receipt_path' => $metadata['receipt_path'] ?? null, 'submitted_at' => $metadata['receipt_uploaded_at'] ?? null, 'rejection_reason' => $metadata['rejection_reason'] ?? null];
        $metadata += ['receipt_disk' => 'local'];
        $metadata['receipt_path'] = $path;
        $metadata['receipt_original_name'] = $receipt->getClientOriginalName();
        $metadata['receipt_mime_type'] = $receipt->getMimeType();
        $metadata['receipt_uploaded_at'] = now()->toIso8601String();
        unset($metadata['rejection_reason'], $metadata['reviewed_by_user_id'], $metadata['reviewed_at']);

        $payment->update(['status' => 'PENDING', 'metadata' => $metadata]);
        $payment->events()->create(['event_type' => 'DEPOSIT_RECEIPT_RESUBMITTED', 'occurred_at' => now()]);
        $order->update(['booking_payment_status' => 'RECEIPT_SUBMITTED']);

        return back()->with('deposit_status', 'Resit baharu telah dihantar untuk semakan.');
    }
}
