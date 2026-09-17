<?php

namespace App\Http\Controllers;

use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CustomerOrderConfirmController extends Controller
{
    public function store(Request $request, string $orderId, CustomerOrderSessionAccessService $access, ConfirmOrderDetailsService $confirm)
    {
        $validated = $request->validate([
            'responsibility_acknowledged' => ['required', 'accepted'],
            'post_confirmation_liability_acknowledged' => ['required', 'accepted'],
            'deposit_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'responsibility_acknowledged.accepted' => 'Anda perlu mengesahkan bahawa semua maklumat telah disemak.',
            'post_confirmation_liability_acknowledged.accepted' => 'Anda perlu bersetuju dengan perakuan kesalahan selepas pengesahan.',
            'deposit_receipt.required' => 'Sila lampirkan resit pembayaran deposit.',
            'deposit_receipt.mimes' => 'Resit mestilah dalam format JPG, JPEG, PNG, WEBP atau PDF.',
            'deposit_receipt.max' => 'Saiz resit tidak boleh melebihi 10 MB.',
        ]);

        $order = $access->resolve($request, $orderId);
        $receipt = $validated['deposit_receipt'];
        $receiptPath = $receipt->store("deposit-receipts/{$order->id}", 'local');

        try {
            $order = DB::transaction(function () use ($order, $confirm, $receipt, $receiptPath) {
                $payment = $order->payments()->updateOrCreate(
                    ['payment_type' => 'BOOKING_DEPOSIT'],
                    [
                        'provider' => 'MANUAL_QR',
                        'amount' => config('kingkadkahwin.deposit.amount'),
                        'currency' => 'MYR',
                        'status' => 'PENDING',
                        'metadata' => [
                            'receipt_disk' => 'local',
                            'receipt_path' => $receiptPath,
                            'receipt_original_name' => $receipt->getClientOriginalName(),
                            'receipt_mime_type' => $receipt->getMimeType(),
                            'receipt_uploaded_at' => now()->toIso8601String(),
                        ],
                    ]
                );

                $confirmedOrder = $confirm->confirm($order);
                $confirmedOrder->update(['booking_payment_status' => 'RECEIPT_SUBMITTED']);
                $confirmedOrder->confirmation()->update([
                    'confirmed_snapshot' => [
                        'responsibility_acknowledged' => true,
                        'post_confirmation_liability_acknowledged' => true,
                        'deposit_payment_id' => $payment->id,
                    ],
                ]);

                return $confirmedOrder->fresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($receiptPath);
            throw $exception;
        }

        return redirect()->route('orders.thank-you.show', ['orderId' => $order->order_id])
            ->with('success', 'Maklumat tempahan telah disahkan.');
    }
}
