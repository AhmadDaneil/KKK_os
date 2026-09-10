<?php

namespace App\Services\Packing;

use App\Models\PackingJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartPackingService
{
    public function start(PackingJob $packingJob, ?User $actor = null): PackingJob
    {
        return DB::transaction(function () use ($packingJob, $actor) {
            $packingJob->refresh();

            if (! in_array($packingJob->status, ['READY_FOR_PACKING', 'PACKING'], true)) {
                throw new RuntimeException(
                    "Packing job {$packingJob->id} cannot start from status {$packingJob->status}."
                );
            }

            if ($packingJob->status === 'PACKING') {
                return $packingJob;
            }

            $packingJob->update([
                'status' => 'PACKING',
                'started_at' => $packingJob->started_at ?? now(),
            ]);

            $packingJob->events()->create([
                'event_type' => 'PACKING_STARTED',
                'from_status' => 'READY_FOR_PACKING',
                'to_status' => 'PACKING',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            return $packingJob->fresh();
        });
    }
}
