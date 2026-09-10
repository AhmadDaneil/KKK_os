<?php

namespace App\Services\Merge;

use App\Models\MergeJob;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateMergeJobsForOrderService
{
    public function __construct(
        private BuildCanonicalMergePayloadService $payloadBuilder,
    ) {}

    public function generate(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();
            $order->load([
                'couples',
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'fulfilment',
                'confirmation',
            ]);

            if ($order->status !== 'DETAILS_CONFIRMED') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be DETAILS_CONFIRMED before merge jobs can be generated."
                );
            }

            if (! $order->confirmation) {
                throw new RuntimeException(
                    "Order {$order->order_id} has no confirmation record."
                );
            }

            $expectedSideCount = (int) $order->package_count;

            if ($order->packageSides->count() !== $expectedSideCount) {
                throw new RuntimeException(
                    "Order {$order->order_id} package side count does not match package_count."
                );
            }

            $jobs = collect();

            foreach ($order->packageSides as $packageSide) {
                $jobId = $this->buildJobId($order->order_id, $packageSide->side);

                $job = MergeJob::updateOrCreate(
                    [
                        'order_package_side_id' => $packageSide->id,
                    ],
                    [
                        'job_id' => $jobId,
                        'order_id' => $order->id,
                        'side' => $packageSide->side,
                        'status' => 'PENDING_EXPORT',
                        'payload_schema_version' => 'kkk_merge_internal_v1',
                        'canonical_payload' => $this->payloadBuilder->build($order, $packageSide),
                        'generated_at' => now(),
                        'exported_at' => null,
                    ]
                );

                $jobs->push($job);
            }

            return $jobs->sortBy('side')->values();
        });
    }

    private function buildJobId(string $orderId, string $side): string
    {
        $suffix = match ($side) {
            'LELAKI' => 'L',
            'PEREMPUAN' => 'P',
            default => throw new RuntimeException("Unsupported package side: {$side}"),
        };

        return "{$orderId}-{$suffix}";
    }
}
