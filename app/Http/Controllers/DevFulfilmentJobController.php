<?php

namespace App\Http\Controllers;

use App\Models\FulfilmentJob;
use App\Models\Order;
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Fulfilment\MarkCourierDeliveredService;
use App\Services\Fulfilment\MarkCourierShippedService;
use App\Services\Fulfilment\MarkPickupCollectedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevFulfilmentJobController extends Controller
{
    public function store(
        string $orderId,
        InitializeFulfilmentJobForOrderService $initializer,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();
        $job = $initializer->initialize($order);

        return response()->json($this->payload($job));
    }

    public function ship(
        Request $request,
        int $fulfilmentJobId,
        MarkCourierShippedService $service,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $validated = $request->validate([
            'courier_provider' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        $job = $service->ship(
            FulfilmentJob::findOrFail($fulfilmentJobId),
            $validated['courier_provider'] ?? null,
            $validated['tracking_number'] ?? null,
        );

        return response()->json($this->payload($job));
    }

    public function deliver(
        Request $request,
        int $fulfilmentJobId,
        MarkCourierDeliveredService $service,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $validated = $request->validate([
            'completion_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $job = $service->deliver(
            FulfilmentJob::findOrFail($fulfilmentJobId),
            $validated['completion_reference'] ?? null,
        );

        return response()->json($this->payload($job));
    }

    public function collect(
        Request $request,
        int $fulfilmentJobId,
        MarkPickupCollectedService $service,
    ): JsonResponse {
        abort_unless(app()->environment('local'), 404);

        $validated = $request->validate([
            'completion_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $job = $service->collect(
            FulfilmentJob::findOrFail($fulfilmentJobId),
            $validated['completion_reference'] ?? null,
        );

        return response()->json($this->payload($job));
    }

    private function payload(FulfilmentJob $job): array
    {
        $job->load('order');

        return [
            'order_id' => $job->order->order_id,
            'order_status' => $job->order->status,
            'fulfilment_job' => [
                'id' => $job->id,
                'method' => $job->method,
                'status' => $job->status,
                'courier_provider' => $job->courier_provider,
                'tracking_number' => $job->tracking_number,
                'completion_reference' => $job->completion_reference,
            ],
        ];
    }
}
