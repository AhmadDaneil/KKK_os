<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Models\Order;
use App\Services\Workflow\ResolveAutomaticAssigneeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeDesignJobsForOrderService
{
    public function __construct(private ResolveAutomaticAssigneeService $assignees) {}

    public function initialize(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();
            $order->load(['mergeJobs', 'packageSides']);

            if ($order->status !== 'DETAILS_CONFIRMED') {
                throw new RuntimeException(
                    "Order {$order->order_id} must be DETAILS_CONFIRMED before designer jobs are initialized."
                );
            }

            if ($order->mergeJobs->count() !== (int) $order->package_count) {
                throw new RuntimeException(
                    "Order {$order->order_id} does not have the expected number of merge jobs."
                );
            }

            $jobs = collect();
            $designLead = $this->assignees->designLead();

            foreach ($order->mergeJobs as $mergeJob) {
                $designJob = DesignJob::firstOrCreate(
                    ['merge_job_id' => $mergeJob->id],
                    [
                        'order_id' => $order->id,
                        'order_package_side_id' => $mergeJob->order_package_side_id,
                        'side' => $mergeJob->side,
                        'status' => 'READY_FOR_DESIGN',
                        'assigned_user_id' => $designLead->id,
                        'assigned_at' => now(),
                    ]
                );

                if ($designJob->wasRecentlyCreated) {
                    $designJob->events()->create([
                        'event_type' => 'DESIGN_JOB_CREATED',
                        'from_status' => null,
                        'to_status' => 'READY_FOR_DESIGN',
                        'occurred_at' => now(),
                        'metadata' => [
                            'merge_job_database_id' => $mergeJob->id,
                            'merge_job_public_id' => $mergeJob->job_id,
                        ],
                    ]);
                    $designJob->events()->create([
                        'event_type' => 'DESIGNER_AUTO_ASSIGNED',
                        'to_status' => 'READY_FOR_DESIGN',
                        'occurred_at' => now(),
                        'metadata' => [
                            'assigned_user_id' => $designLead->id,
                            'assignment_mode' => 'AUTOMATIC_DESIGN_LEAD',
                        ],
                    ]);
                } elseif ($designJob->assigned_user_id === null) {
                    $designJob->update([
                        'assigned_user_id' => $designLead->id,
                        'assigned_at' => now(),
                    ]);
                    $designJob->events()->create([
                        'event_type' => 'DESIGNER_AUTO_ASSIGNED',
                        'to_status' => $designJob->status,
                        'occurred_at' => now(),
                        'metadata' => [
                            'assigned_user_id' => $designLead->id,
                            'assignment_mode' => 'AUTOMATIC_DESIGN_LEAD',
                        ],
                    ]);
                }

                $jobs->push($designJob);
            }

            return $jobs->sortBy('side')->values();
        });
    }
}
