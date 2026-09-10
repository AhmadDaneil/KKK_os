<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Printing\InitializePrintJobsForOrderService;
use Illuminate\Http\JsonResponse;

class DevPrintJobController extends Controller
{
    public function store(
        string $orderId,
        InitializePrintJobsForOrderService $initializer,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();

        $jobs = $initializer->initialize($order);

        return response()->json([
            'order_id' => $order->order_id,
            'print_job_count' => $jobs->count(),
            'print_jobs' => $jobs->map(fn ($job) => [
                'id' => $job->id,
                'side' => $job->side,
                'status' => $job->status,
                'artwork_version_id' => $job->artwork_version_id,
            ])->values(),
        ]);
    }
}
