<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\PrintJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializePrintJobsForOrderService
{
    public function initialize(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();
            $order->load([
                'designJobs.artworkVersions',
            ]);

            if ($order->status !== 'PAID') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be PAID before print jobs can be initialized."
                );
            }

            if ($order->designJobs->count() !== (int) $order->package_count) {
                throw new RuntimeException(
                    "Order {$order->order_id} does not have the expected number of design jobs."
                );
            }

            $jobs = collect();

            foreach ($order->designJobs as $designJob) {
                if ($designJob->status !== 'DESIGN_APPROVED') {
                    throw new RuntimeException(
                        "Design job {$designJob->id} must be DESIGN_APPROVED before printing."
                    );
                }

                $latestArtwork = $designJob->artworkVersions
                    ->sortByDesc('version_number')
                    ->first();

                if (! $latestArtwork) {
                    throw new RuntimeException(
                        "Design job {$designJob->id} has no artwork version."
                    );
                }

                $printJob = PrintJob::firstOrCreate(
                    [
                        'design_job_id' => $designJob->id,
                    ],
                    [
                        'order_id' => $order->id,
                        'order_package_side_id' => $designJob->order_package_side_id,
                        'artwork_version_id' => $latestArtwork->id,
                        'side' => $designJob->side,
                        'status' => 'READY_FOR_PRINT',
                    ]
                );

                if ($printJob->wasRecentlyCreated) {
                    $printJob->events()->create([
                        'event_type' => 'PRINT_JOB_CREATED',
                        'from_status' => null,
                        'to_status' => 'READY_FOR_PRINT',
                        'occurred_at' => now(),
                        'metadata' => [
                            'design_job_id' => $designJob->id,
                            'artwork_version_id' => $latestArtwork->id,
                            'artwork_version_number' => $latestArtwork->version_number,
                        ],
                    ]);
                }

                $jobs->push($printJob);
            }

            return $jobs->sortBy('side')->values();
        });
    }
}
