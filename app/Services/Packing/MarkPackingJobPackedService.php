<?php

namespace App\Services\Packing;

use App\Models\PackingJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkPackingJobPackedService
{
    public function markPacked(PackingJob $packingJob, ?User $actor = null): PackingJob
    {
        return DB::transaction(function () use ($packingJob, $actor) {
            $packingJob->refresh()->load('items');

            if ($packingJob->status !== 'PACKING') {
                throw new RuntimeException(
                    "Packing job {$packingJob->id} must be PACKING before it can become PACKED."
                );
            }

            if ($packingJob->items->isEmpty()) {
                throw new RuntimeException(
                    "Packing job {$packingJob->id} has no packing items."
                );
            }

            if ($packingJob->items->contains(fn ($item) => ! $item->verified_present)) {
                throw new RuntimeException(
                    "All packing items must be verified before packing job {$packingJob->id} can become PACKED."
                );
            }

            $packingJob->update([
                'status' => 'PACKED',
                'packed_at' => now(),
            ]);

            $packingJob->events()->create([
                'event_type' => 'PACKING_COMPLETED',
                'from_status' => 'PACKING',
                'to_status' => 'PACKED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'verified_item_count' => $packingJob->items->count(),
                ],
            ]);

            $packingJob->order()->update([
                'status' => 'PACKED',
            ]);

            return $packingJob->fresh(['items']);
        });
    }
}
