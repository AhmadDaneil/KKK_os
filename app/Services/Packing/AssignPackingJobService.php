<?php

namespace App\Services\Packing;

use App\Models\PackingJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignPackingJobService
{
    public function assign(
        PackingJob $packingJob,
        User $assignee,
        ?User $actor = null
    ): PackingJob {
        $actor ??= $assignee;

        return DB::transaction(function () use ($packingJob, $assignee, $actor) {
            $previousUserId = $packingJob->assigned_user_id;

            $packingJob->update([
                'assigned_user_id' => $assignee->id,
                'assigned_at' => now(),
            ]);

            $packingJob->events()->create([
                'event_type' => 'PACKING_JOB_ASSIGNED',
                'actor_user_id' => $actor->id,
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousUserId,
                    'assigned_user_id' => $assignee->id,
                ],
            ]);

            return $packingJob->fresh();
        });
    }
}