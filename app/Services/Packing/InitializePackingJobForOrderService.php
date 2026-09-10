<?php

namespace App\Services\Packing;

use App\Models\Order;
use App\Models\PackingJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializePackingJobForOrderService
{
    public function initialize(Order $order): PackingJob
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();
            $order->load('printJobs');

            if ($order->status !== 'PRINTED') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be PRINTED before packing can be initialized."
                );
            }

            if ($order->printJobs->count() !== (int) $order->package_count) {
                throw new RuntimeException(
                    "Order {$order->order_id} does not have the expected number of print jobs."
                );
            }

            if ($order->printJobs->contains(fn ($job) => $job->status !== 'PRINTED')) {
                throw new RuntimeException(
                    "All print jobs for order {$order->order_id} must be PRINTED before packing."
                );
            }

            $packingJob = PackingJob::firstOrCreate(
                ['order_id' => $order->id],
                ['status' => 'READY_FOR_PACKING']
            );

            if ($packingJob->wasRecentlyCreated) {
                foreach ($order->printJobs as $printJob) {
                    $packingJob->items()->create([
                        'print_job_id' => $printJob->id,
                        'order_package_side_id' => $printJob->order_package_side_id,
                        'side' => $printJob->side,
                    ]);
                }

                $packingJob->events()->create([
                    'event_type' => 'PACKING_JOB_CREATED',
                    'from_status' => null,
                    'to_status' => 'READY_FOR_PACKING',
                    'occurred_at' => now(),
                    'metadata' => [
                        'item_count' => $order->printJobs->count(),
                    ],
                ]);
            }

            return $packingJob->fresh(['items', 'events']);
        });
    }
}
