<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Packing\InitializePackingJobForOrderService;
use Illuminate\Http\JsonResponse;

class DevPackingJobController extends Controller
{
    public function store(
        string $orderId,
        InitializePackingJobForOrderService $initializer,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();
        $job = $initializer->initialize($order);

        return response()->json([
            'order_id' => $order->order_id,
            'packing_job' => [
                'id' => $job->id,
                'status' => $job->status,
                'item_count' => $job->items->count(),
                'items' => $job->items->sortBy('side')->values()->map(fn ($item) => [
                    'id' => $item->id,
                    'side' => $item->side,
                    'verified_present' => $item->verified_present,
                ]),
            ],
        ]);
    }
}
