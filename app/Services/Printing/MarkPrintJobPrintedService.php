<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkPrintJobPrintedService
{
    public function markPrinted(PrintJob $printJob, ?User $actor = null): PrintJob
    {
        return DB::transaction(function () use ($printJob, $actor) {
            $printJob->refresh();

            if ($printJob->status !== 'PRINTING') {
                throw new RuntimeException(
                    "Print job {$printJob->id} must be PRINTING before it can become PRINTED."
                );
            }

            $printJob->update([
                'status' => 'PRINTED',
                'printed_at' => now(),
            ]);

            $printJob->events()->create([
                'event_type' => 'PRINTING_COMPLETED',
                'from_status' => 'PRINTING',
                'to_status' => 'PRINTED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            return $printJob->fresh();
        });
    }
}
