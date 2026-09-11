<?php

namespace App\Http\Controllers;

use App\Models\DesignJob;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Orders\ResolveOrderAccessService;
use Illuminate\Http\Request;

class CustomerArtworkReviewController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $access,
    ) {
        $order = $access->resolve($orderId, $request->query('token'));

        $order->load([
            'designJobs.artworkVersions',
            'designJobs.reviewActions',
        ]);

        return view('orders.artwork-review', [
            'order' => $order,
            'plainToken' => $request->query('token'),
        ]);
    }

    public function correction(
        Request $request,
        string $orderId,
        int $designJobId,
        ResolveOrderAccessService $access,
        RequestArtworkCorrectionService $correction,
        SyncOrderDesignStatusService $sync,
    ) {
        $order = $access->resolve($orderId, $request->input('token'));

        $validated = $request->validate([
            'correction_comment' => ['required', 'string', 'max:5000'],
        ]);

        $designJob = DesignJob::where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        $correction->request($designJob, $validated['correction_comment']);
        $sync->sync($order);

        return redirect()
            ->route('orders.artwork.review', [
                'orderId' => $order->order_id,
                'token' => $request->input('token'),
            ])
            ->with('success', 'Permintaan pembetulan telah dihantar.');
    }

    public function approve(
        Request $request,
        string $orderId,
        int $designJobId,
        ResolveOrderAccessService $access,
        ApproveArtworkService $approve,
        SyncOrderDesignStatusService $sync,
    ) {
        $order = $access->resolve($orderId, $request->input('token'));

        $designJob = DesignJob::where('order_id', $order->id)
            ->whereKey($designJobId)
            ->firstOrFail();

        $approve->approve($designJob);
        $sync->sync($order);

        return redirect()
            ->route('orders.artwork.review', [
                'orderId' => $order->order_id,
                'token' => $request->input('token'),
            ])
            ->with('success', 'Artwork telah diluluskan.');
    }
}
