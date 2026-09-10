<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use Illuminate\Http\JsonResponse;

class DevMergeJobController extends Controller
{
    public function store(
        string $orderId,
        GenerateMergeJobsForOrderService $generator,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();

        $jobs = $generator->generate($order);

        return response()->json([
            'order_id' => $order->order_id,
            'job_count' => $jobs->count(),
            'jobs' => $jobs->map(fn ($job) => [
                'job_id' => $job->job_id,
                'side' => $job->side,
                'status' => $job->status,
                'payload_schema_version' => $job->payload_schema_version,
            ])->values(),
        ]);
    }
}
