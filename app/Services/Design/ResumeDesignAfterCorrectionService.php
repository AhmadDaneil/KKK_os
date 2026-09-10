<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResumeDesignAfterCorrectionService
{
    public function resume(DesignJob $designJob, ?User $actor = null): DesignJob
    {
        return DB::transaction(function () use ($designJob, $actor) {
            $designJob->refresh();

            if ($designJob->status !== 'CORRECTION_REQUESTED') {
                throw new RuntimeException(
                    "Design job {$designJob->id} must be CORRECTION_REQUESTED before designer resumes work."
                );
            }

            $designJob->update([
                'status' => 'DESIGN_IN_PROGRESS',
            ]);

            $designJob->events()->create([
                'event_type' => 'CORRECTION_WORK_STARTED',
                'from_status' => 'CORRECTION_REQUESTED',
                'to_status' => 'DESIGN_IN_PROGRESS',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            return $designJob->fresh();
        });
    }
}
