<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkDesignReadyService
{
    public function markReady(DesignJob $designJob, ?User $actor = null): DesignJob
    {
        return DB::transaction(function () use ($designJob, $actor) {
            $designJob->refresh()->load('artworkVersions');

            if ($designJob->status !== 'DESIGN_IN_PROGRESS') {
                throw new RuntimeException(
                    "Design job {$designJob->id} must be DESIGN_IN_PROGRESS before it can become DESIGN_READY."
                );
            }

            $latestArtwork = $designJob->artworkVersions()
                ->orderByDesc('version_number')
                ->first();

            if (! $latestArtwork) {
                throw new RuntimeException(
                    "Design job {$designJob->id} has no artwork version to review."
                );
            }

            $fromStatus = $designJob->status;

            $designJob->update([
                'status' => 'DESIGN_READY',
                'design_ready_at' => now(),
            ]);

            $designJob->events()->create([
                'event_type' => 'DESIGN_READY',
                'from_status' => $fromStatus,
                'to_status' => 'DESIGN_READY',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'artwork_version_id' => $latestArtwork->id,
                    'version_number' => $latestArtwork->version_number,
                ],
            ]);

            $designJob->reviewActions()->create([
                'artwork_version_id' => $latestArtwork->id,
                'action' => 'DESIGN_READY',
                'actor_user_id' => $actor?->id,
                'acted_at' => now(),
            ]);

            return $designJob->fresh();
        });
    }
}
