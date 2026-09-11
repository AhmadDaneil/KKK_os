<?php

namespace App\Http\Controllers;

use App\Models\DesignJob;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;

class CustomerArtworkReviewController extends Controller
{
    public function show(Request $request, string $orderId, CustomerOrderSessionAccessService $access)
    {
        $order = $access->resolve($request, $orderId);
        $order->load(['designJobs.artworkVersions', 'designJobs.reviewActions']);
        return view('orders.artwork-review', ['order' => $order]);
    }

    public function correction(Request $request, string $orderId, int $designJobId, CustomerOrderSessionAccessService $access, RequestArtworkCorrectionService $correction, SyncOrderDesignStatusService $sync)
    {
        $order = $access->resolve($request, $orderId);
        $validated = $request->validate(['correction_comment' => ['required', 'string', 'max:5000']]);
        $designJob = DesignJob::where('order_id', $order->id)->whereKey($designJobId)->firstOrFail();
        $correction->request($designJob, $validated['correction_comment']);
        $sync->sync($order);
        return redirect()->route('orders.artwork.review', ['orderId' => $order->order_id])
            ->with('success', 'Permintaan pembetulan telah dihantar.');
    }

    public function approve(Request $request, string $orderId, int $designJobId, CustomerOrderSessionAccessService $access, ApproveArtworkService $approve, SyncOrderDesignStatusService $sync)
    {
        $order = $access->resolve($request, $orderId);
        $designJob = DesignJob::where('order_id', $order->id)->whereKey($designJobId)->firstOrFail();
        $approve->approve($designJob);
        $sync->sync($order);
        return redirect()->route('orders.artwork.review', ['orderId' => $order->order_id])
            ->with('success', 'Artwork telah diluluskan.');
    }
}
