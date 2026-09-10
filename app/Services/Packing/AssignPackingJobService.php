<?php

namespace App\Services\Packing;

use App\Models\PackingJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignPackingJobService
{
    public function assign(PackingJob $packingJob, User $user): PackingJob
    {
        return DB::transaction(function () use ($packingJob, $user) {
            $previousUserId = $packingJob->assigned_user_id;

            $packingJob->update([
                'assigned_user_id' => $user->id,
                'assigned_at' => now(),
            ]);

            $packingJob->events()->create([
                'event_type' => 'PACKING_JOB_ASSIGNED',
                'actor_user_id' => $user->id,
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousUserId,
                    'assigned_user_id' => $user->id,
                ],
            ]);

            return $packingJob->fresh();
        });
    }
}
