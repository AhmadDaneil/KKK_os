<?php

namespace App\Http\Controllers;

use App\Models\DesignJob;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ?: $artwork->mime_type
            ?: 'application/octet-stream';

        $filename = $artwork->original_filename
            ?: basename($path);

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
                'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
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
        CustomerOrderSessionAccessService $access,
        RequestArtworkCorrectionService $correction,
        SyncOrderDesignStatusService $sync
    ) {
        $order = $access->resolve($request, $orderId);

        $validated = $request->validate([
            'correction_comment' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $designJob = DesignJob::query()
            ->where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        $correction->request(
            $designJob,
            $validated['correction_comment']
        );

        $sync->sync($order);

        return redirect()
            ->route('orders.artwork.review', [
                'orderId' => $order->order_id,
            ])
            ->with(
                'success',
                'Permintaan pembetulan telah dihantar.'
            );
    }

    public function approve(
        Request $request,
        string $orderId,
        int $designJobId,
        CustomerOrderSessionAccessService $access,
        ApproveArtworkService $approve,
        SyncOrderDesignStatusService $sync
    ) {
        $order = $access->resolve($request, $orderId);

        $designJob = DesignJob::query()
            ->where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        $approve->approve($designJob);

        $sync->sync($order);

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