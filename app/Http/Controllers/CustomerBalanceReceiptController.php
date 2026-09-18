<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CustomerBalanceReceiptController extends Controller
{
    public function store(Request $request, string $orderId, CustomerOrderSessionAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'balance_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'balance_receipt.required' => 'Sila lampirkan resit pembayaran penuh.',
            'balance_receipt.mimes' => 'Resit mestilah dalam format JPG, JPEG, PNG, WEBP atau PDF.',
            'balance_receipt.max' => 'Saiz resit tidak boleh melebihi 10 MB.',
        ]);

        $order = $access->resolve($request, $orderId);
        abort_unless(in_array($order->status, ['DESIGN_APPROVED', 'BALANCE_PENDING'], true), 422);

        $existing = $order->payments()->where('payment_type', 'BALANCE')->latest('id')->first();
        abort_if($existing && in_array($existing->status, ['PENDING', 'PAID'], true), 422);

        $receipt = $validated['balance_receipt'];
        $receiptPath = $receipt->store("balance-receipts/{$order->id}", 'local');

        try {
            DB::transaction(function () use ($order, $existing, $receipt, $receiptPath) {
                $metadata = $existing?->metadata ?? [];
                if ($existing) {
                    $metadata['receipt_history'][] = [
                        'receipt_path' => $metadata['receipt_path'] ?? null,
                        'submitted_at' => $metadata['receipt_uploaded_at'] ?? null,
                        'rejection_reason' => $metadata['rejection_reason'] ?? null,
                    ];
                }

                $metadata['receipt_disk'] = 'local';
                $metadata['receipt_path'] = $receiptPath;
                $metadata['receipt_original_name'] = $receipt->getClientOriginalName();
                $metadata['receipt_mime_type'] = $receipt->getMimeType();
                $metadata['receipt_uploaded_at'] = now()->toIso8601String();
                unset($metadata['rejection_reason'], $metadata['reviewed_by_user_id'], $metadata['reviewed_at']);

                $payment = $existing ?: $order->payments()->make(['payment_type' => 'BALANCE']);
                $payment->fill([
                    'provider' => 'MANUAL_QR',
                    'amount' => config('kingkadkahwin.balance.amount'),
                    'currency' => 'MYR',
                    'status' => 'PENDING',
                    'paid_at' => null,
                    'metadata' => $metadata,
                ])->save();

                $payment->events()->create([
                    'event_type' => $existing ? 'BALANCE_RECEIPT_RESUBMITTED' : 'BALANCE_RECEIPT_SUBMITTED',
                    'occurred_at' => now(),
                ]);
                $order->update(['status' => 'BALANCE_PENDING']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($receiptPath);
            throw $exception;
        }

        return redirect()
            ->route('public.orders.progress', ['order_id' => $order->order_id])
            ->with('balance_success', 'Resit pembayaran penuh telah dihantar untuk semakan.');
    }
}
