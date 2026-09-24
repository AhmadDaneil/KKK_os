<?php

namespace App\Http\Controllers;

use App\Models\DesignJob;
use App\Models\Order;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerArtworkReviewController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access
    ) {
        $order = $access->resolve($request, $orderId);

        $order->load([
            'designJobs.artworkVersions',
            'designJobs.reviewActions',
            'payments',
        ]);

        return view('orders.artwork-review', [
            'order' => $order,
        ]);
    }

    public function preview(
        Request $request,
        string $orderId,
        int $designJobId,
        CustomerOrderSessionAccessService $access
    ): StreamedResponse {
        $order = $access->resolve($request, $orderId);

        $designJob = DesignJob::query()
            ->where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        if (! in_array($designJob->status, [
            'DESIGN_READY',
            'CORRECTION_REQUESTED',
            'DESIGN_APPROVED',
        ], true)) {
            abort(404);
        }

        $artwork = $designJob->artworkVersions()
            ->orderByDesc('version_number')
            ->firstOrFail();

        $disk = $artwork->storage_disk ?: 'local';

        $path = $artwork->preview_storage_path;

        abort_unless(
            filled($path)
            && Storage::disk($disk)->exists($path),
            404
        );

        $storage = Storage::disk($disk);

        $stream = $storage->readStream($path);

        abort_if($stream === false, 404);

        $mimeType = $storage->mimeType($path)
            ?: 'application/octet-stream';

        $filename = basename($path);

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function correction(
        Request $request,
        string $orderId,
        int $designJobId,
        CustomerOrderSessionAccessService $access
    ) {
        $order = $access->resolve($request, $orderId);

        $designJob = DesignJob::where('order_id', $order->id)->findOrFail($designJobId);

        $validated = $request->validate([
            'correction_fee_agreed' => ['accepted'],
            'correction_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'correction_comment' => [
                'required',
                'string',
                'max:5000',
            ],
        ], [
            'correction_fee_agreed.accepted' => 'Sila bersetuju dengan caj pembetulan RM10.',
            'correction_receipt.required' => 'Sila lampirkan resit bayaran pembetulan RM10.',
            'correction_receipt.mimes' => 'Resit mestilah JPG, JPEG, PNG, WEBP atau PDF.',
            'correction_receipt.max' => 'Saiz resit tidak boleh melebihi 10 MB.',
        ]);

        $receipt = $validated['correction_receipt'];
        $path = null;
        try {
            DB::transaction(function () use ($order, $designJob, $validated, $receipt, &$path) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $designJob->refresh();
                abort_unless(! $order->isTerminalOperationalStatus() && $designJob->status === 'DESIGN_READY', 422);
                if ($order->payments()->where('payment_type', 'ARTWORK_CORRECTION')->where('status', 'PENDING')
                    ->where('metadata->design_job_id', $designJob->id)->exists()) {
                    throw ValidationException::withMessages(['correction_receipt' => 'Bayaran pembetulan ini masih menunggu pengesahan.']);
                }
                $artwork = $designJob->artworkVersions()->latest('version_number')->firstOrFail();
                $path = $receipt->store('correction-receipts/'.$order->id, 'local');
                $payment = $order->payments()->create([
                    'payment_type' => 'ARTWORK_CORRECTION', 'provider' => 'MANUAL_QR',
                    'amount' => '10.00', 'currency' => 'MYR', 'status' => 'PENDING',
                    'metadata' => [
                        'design_job_id' => $designJob->id, 'artwork_version_id' => $artwork->id,
                        'correction_comment' => trim($validated['correction_comment']),
                        'receipt_path' => $path, 'receipt_original_name' => $receipt->getClientOriginalName(),
                        'receipt_mime_type' => $receipt->getMimeType(),
                        'fee_agreed_at' => now()->toIso8601String(),
                    ],
                ]);
                $payment->events()->create(['event_type' => 'CORRECTION_RECEIPT_SUBMITTED', 'payload' => [], 'occurred_at' => now()]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()
            ->route('orders.artwork.review', [
                'orderId' => $order->order_id,
            ])
            ->with(
                'success',
                'Permintaan diterima. Menunggu pengesahan bayaran RM10 sebelum pembetulan dimulakan.'
            );
    }

    public function approve(
        Request $request,
        string $orderId,
        int $designJobId,
        CustomerOrderSessionAccessService $access,
        ApproveArtworkService $approve,
        SyncOrderDesignStatusService $sync,
        InitializePrintJobsForOrderService $initializePrintJobs
    ) {
        $order = $access->resolve($request, $orderId);

        $designJob = DesignJob::query()
            ->where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        DB::transaction(function () use ($order, $designJob, $approve, $sync, $initializePrintJobs) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payments()->where('payment_type', 'ARTWORK_CORRECTION')->where('status', 'PENDING')
                ->where('metadata->design_job_id', $designJob->id)->exists()) {
                throw ValidationException::withMessages(['artwork' => 'Bayaran pembetulan masih menunggu pengesahan.']);
            }
            $approve->approve($designJob);
            $order = $sync->sync($order);
            if ($order->status === 'DESIGN_APPROVED') {
                $initializePrintJobs->initialize($order);
            }
        });

        return redirect()
            ->route('orders.artwork.review', [
                'orderId' => $order->order_id,
            ])
            ->with(
                'success',
                'Artwork telah diluluskan.'
            );
    }
}
