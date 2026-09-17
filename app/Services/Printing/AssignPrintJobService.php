<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignPrintJobService
{
    public function assign(
        PrintJob $printJob,
        User $assignee,
        ?User $actor = null
    ): PrintJob {
        $actor ??= $assignee;

        return DB::transaction(function () use ($printJob, $assignee, $actor) {
            $previousUserId = $printJob->assigned_user_id;

            $printJob->update([
                'assigned_user_id' => $assignee->id,
                'assigned_at' => now(),
            ]);

            $printJob->events()->create([
                'event_type' => 'PRINT_JOB_ASSIGNED',
                'actor_user_id' => $actor->id,
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousUserId,
                    'assigned_user_id' => $assignee->id,
                ],
            ]);

            return $printJob->fresh();
        });
    }
}