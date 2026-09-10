<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignPrintJobService
{
    public function assign(PrintJob $printJob, User $user): PrintJob
    {
        return DB::transaction(function () use ($printJob, $user) {
            $previousUserId = $printJob->assigned_user_id;

            $printJob->update([
                'assigned_user_id' => $user->id,
                'assigned_at' => now(),
            ]);

            $printJob->events()->create([
                'event_type' => 'PRINT_JOB_ASSIGNED',
                'actor_user_id' => $user->id,
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousUserId,
                    'assigned_user_id' => $user->id,
                ],
            ]);

            return $printJob->fresh();
        });
    }
}
