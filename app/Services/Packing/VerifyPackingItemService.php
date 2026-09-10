<?php

namespace App\Services\Packing;

use App\Models\PackingJobItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VerifyPackingItemService
{
    public function verify(PackingJobItem $item, ?User $actor = null): PackingJobItem
    {
        return DB::transaction(function () use ($item, $actor) {
            $item->refresh()->load('packingJob');

            if ($item->packingJob->status !== 'PACKING') {
                throw new RuntimeException(
                    "Packing item {$item->id} can only be verified while packing is in progress."
                );
            }

            if ($item->verified_present) {
                return $item;
            }

            $item->update([
                'verified_present' => true,
                'verified_at' => now(),
            ]);

            $item->packingJob->events()->create([
                'event_type' => 'PACKING_ITEM_VERIFIED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'packing_job_item_id' => $item->id,
                    'side' => $item->side,
                    'print_job_id' => $item->print_job_id,
                ],
            ]);

            return $item->fresh();
        });
    }
}
