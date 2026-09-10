<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Design\InitializeDesignJobsForOrderService;
use Illuminate\Http\JsonResponse;

class DevDesignJobController extends Controller
{
    public function store(string $orderId, InitializeDesignJobsForOrderService $initializer): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();
        $jobs = $initializer->initialize($order);

        return response()->json([
            'order_id' => $order->order_id,
            'design_job_count' => $jobs->count(),
            'design_jobs' => $jobs->map(fn ($job) => [
                'id' => $job->id,
                'side' => $job->side,
                'status' => $job->status,
                'merge_job_id' => $job->mergeJob?->job_id,
            ])->values(),
        ]);
    }
}
