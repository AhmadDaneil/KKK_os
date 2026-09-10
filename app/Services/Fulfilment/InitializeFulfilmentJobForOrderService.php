<?php

namespace App\Services\Fulfilment;

use App\Models\FulfilmentJob;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeFulfilmentJobForOrderService
{
    public function initialize(Order $order): FulfilmentJob
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();
            $order->load(['fulfilment', 'packingJob']);

            if ($order->status !== 'PACKED') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be PACKED before fulfilment can be initialized."
                );
            }

            if (! $order->packingJob || $order->packingJob->status !== 'PACKED') {
                throw new RuntimeException(
                    "Order {$order->order_id} does not have a completed packing job."
                );
            }

            if (! $order->fulfilment) {
                throw new RuntimeException(
                    "Order {$order->order_id} has no fulfilment information."
                );
            }

            if (! in_array($order->fulfilment->method, ['COURIER', 'PICKUP'], true)) {
                throw new RuntimeException(
                    "Order {$order->order_id} has an unsupported fulfilment method."
                );
            }

            if ($order->fulfilment->method === 'COURIER') {
                if (
                    ! $order->fulfilment->recipient_name ||
                    ! $order->fulfilment->recipient_phone ||
                    ! $order->fulfilment->shipping_address
                ) {
                    throw new RuntimeException(
                        "Courier fulfilment for order {$order->order_id} is missing recipient/shipping information."
                    );
                }
            }

            $job = FulfilmentJob::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'order_fulfilment_id' => $order->fulfilment->id,
                    'packing_job_id' => $order->packingJob->id,
                    'method' => $order->fulfilment->method,
                    'status' => 'READY',
                ]
            );

            if ($job->wasRecentlyCreated) {
                $job->events()->create([
                    'event_type' => 'FULFILMENT_JOB_CREATED',
                    'from_status' => null,
                    'to_status' => 'READY',
                    'occurred_at' => now(),
                    'metadata' => [
                        'method' => $job->method,
                    ],
                ]);
            }

            return $job->fresh(['events']);
        });
    }
}
