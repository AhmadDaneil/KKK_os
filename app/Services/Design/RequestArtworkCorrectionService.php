<?php

namespace App\Services\Design;

use App\Models\DesignJob;
use App\Services\Workflow\ResolveAutomaticAssigneeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RequestArtworkCorrectionService
{
    public function __construct(private ResolveAutomaticAssigneeService $assignees) {}

    /** @param array<int, string> $affectedAssets */
    public function request(DesignJob $designJob, string $comment, array $affectedAssets = ['CARD']): DesignJob
    {
        return DB::transaction(function () use ($designJob, $comment, $affectedAssets) {
            $designJob->refresh();

            $designJob->loadMissing('order');

            if ($designJob->order->isTerminalOperationalStatus()) {
                throw new RuntimeException(
                    "Correction cannot be requested because order {$designJob->order->order_id} is terminal."
                );
            }

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
            $previousDesignerId = $designJob->assigned_user_id;
            $backupDesigner = $this->assignees->correctionDesigner($previousDesignerId);

            $designJob->update([
                'status' => 'CORRECTION_REQUESTED',
                'assigned_user_id' => $backupDesigner->id,
                'assigned_at' => now(),
            ]);

            $designJob->reviewActions()->create([
                'artwork_version_id' => $latestArtwork->id,
                'action' => 'CORRECTION_REQUESTED',
                'customer_comment' => $comment,
                'affected_assets' => array_values(array_unique($affectedAssets)),
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
                    'affected_assets' => array_values(array_unique($affectedAssets)),
                    'previous_assigned_user_id' => $previousDesignerId,
                    'assigned_user_id' => $backupDesigner->id,
                ],
            ]);

            $designJob->events()->create([
                'event_type' => 'CORRECTION_DESIGNER_AUTO_ASSIGNED',
                'from_status' => 'CORRECTION_REQUESTED',
                'to_status' => 'CORRECTION_REQUESTED',
                'occurred_at' => now(),
                'metadata' => [
                    'previous_assigned_user_id' => $previousDesignerId,
                    'assigned_user_id' => $backupDesigner->id,
                    'assignment_mode' => 'AUTOMATIC_BACKUP_DESIGNER',
                ],
            ]);

            return $designJob->fresh();
        });
    }
}
