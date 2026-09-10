<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RequestArtworkCorrectionService
{
    public function request(DesignJob $designJob, string $comment): DesignJob
    {
        return DB::transaction(function () use ($designJob, $comment) {
            $designJob->refresh();

            if ($designJob->status !== 'DESIGN_READY') {
                throw new RuntimeException(
                    "Correction can only be requested when design job {$designJob->id} is DESIGN_READY."
                );
            }

            $comment = trim($comment);

            if ($comment === '') {
                throw ValidationException::withMessages([
                    'correction_comment' => 'Sila nyatakan pembetulan yang diperlukan.',
                ]);
            }

            $latestArtwork = $designJob->artworkVersions()
                ->orderByDesc('version_number')
                ->firstOrFail();

            $fromStatus = $designJob->status;

            $designJob->update([
                'status' => 'CORRECTION_REQUESTED',
            ]);

            $designJob->reviewActions()->create([
                'artwork_version_id' => $latestArtwork->id,
                'action' => 'CORRECTION_REQUESTED',
                'customer_comment' => $comment,
                'acted_at' => now(),
            ]);

            $designJob->events()->create([
                'event_type' => 'CORRECTION_REQUESTED',
                'from_status' => $fromStatus,
                'to_status' => 'CORRECTION_REQUESTED',
                'occurred_at' => now(),
                'metadata' => [
                    'artwork_version_id' => $latestArtwork->id,
                    'version_number' => $latestArtwork->version_number,
                    'customer_comment' => $comment,
                ],
            ]);

            return $designJob->fresh();
        });
    }
}
