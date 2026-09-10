<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartDesignJobService
{
    public function start(DesignJob $designJob, ?User $actor = null): DesignJob
    {
        return DB::transaction(function () use ($designJob, $actor) {
            $designJob->refresh();

            if (! in_array($designJob->status, ['READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS'], true)) {
                throw new RuntimeException(
                    "Design job {$designJob->id} cannot be started from status {$designJob->status}."
                );
            }

            if ($designJob->status === 'DESIGN_IN_PROGRESS') {
                return $designJob;
            }

            $fromStatus = $designJob->status;

            $designJob->update([
                'status' => 'DESIGN_IN_PROGRESS',
                'started_at' => $designJob->started_at ?? now(),
            ]);

            $designJob->events()->create([
                'event_type' => 'DESIGN_STARTED',
                'from_status' => $fromStatus,
                'to_status' => 'DESIGN_IN_PROGRESS',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            return $designJob->fresh();
        });
    }
}
