<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignDesignJobService
{
    public function assign(
        DesignJob $designJob,
        User $assignee,
        ?User $actor = null
    ): DesignJob {
        $actor ??= $assignee;

        return DB::transaction(function () use ($designJob, $assignee, $actor) {
            $previousUserId = $designJob->assigned_user_id;

            $designJob->update([
                'assigned_user_id' => $assignee->id,
                'assigned_at' => now(),
            ]);

            $designJob->events()->create([
                'event_type' => 'DESIGNER_ASSIGNED',
                'actor_user_id' => $actor->id,
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousUserId,
                    'assigned_user_id' => $assignee->id,
                ],
            ]);

            return $designJob->fresh();
        });
    }
}