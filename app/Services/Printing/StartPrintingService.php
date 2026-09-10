<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartPrintingService
{
    public function start(PrintJob $printJob, ?User $actor = null): PrintJob
    {
        return DB::transaction(function () use ($printJob, $actor) {
            $printJob->refresh();

            if (! in_array($printJob->status, ['READY_FOR_PRINT', 'PRINTING'], true)) {
                throw new RuntimeException(
                    "Print job {$printJob->id} cannot start from status {$printJob->status}."
                );
            }

            if ($printJob->status === 'PRINTING') {
                return $printJob;
            }

            $fromStatus = $printJob->status;

            $printJob->update([
                'status' => 'PRINTING',
                'started_at' => $printJob->started_at ?? now(),
            ]);

            $printJob->events()->create([
                'event_type' => 'PRINTING_STARTED',
                'from_status' => $fromStatus,
                'to_status' => 'PRINTING',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            return $printJob->fresh();
        });
    }
}
