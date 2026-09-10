<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApproveArtworkService
{
    public function approve(DesignJob $designJob): DesignJob
    {
        return DB::transaction(function () use ($designJob) {
            $designJob->refresh();

            if ($designJob->status !== 'DESIGN_READY') {
                throw new RuntimeException(
                    "Design job {$designJob->id} can only be approved from DESIGN_READY."
                );
            }

            $latestArtwork = $designJob->artworkVersions()
                ->orderByDesc('version_number')
                ->firstOrFail();

            $designJob->update([
                'status' => 'DESIGN_APPROVED',
            ]);

            $designJob->reviewActions()->create([
                'artwork_version_id' => $latestArtwork->id,
                'action' => 'DESIGN_APPROVED',
                'acted_at' => now(),
            ]);

            $designJob->events()->create([
                'event_type' => 'DESIGN_APPROVED',
                'from_status' => 'DESIGN_READY',
                'to_status' => 'DESIGN_APPROVED',
                'occurred_at' => now(),
                'metadata' => [
                    'artwork_version_id' => $latestArtwork->id,
                    'version_number' => $latestArtwork->version_number,
                ],
            ]);

            return $designJob->fresh();
        });
    }
}
