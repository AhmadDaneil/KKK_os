<?php

namespace App\Services\Fulfilment;

use App\Models\FulfilmentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkCourierDeliveredService
{
    public function deliver(
        FulfilmentJob $job,
        ?string $completionReference = null,
        ?User $actor = null,
    ): FulfilmentJob {
        return DB::transaction(function () use ($job, $completionReference, $actor) {
            $job->refresh();

            if ($job->method !== 'COURIER') {
                throw new RuntimeException(
                    "Fulfilment job {$job->id} is not a COURIER job."
                );
            }

            if ($job->status === 'DELIVERED') {
                return $job;
            }

            if ($job->status !== 'SHIPPED') {
                throw new RuntimeException(
                    "Courier job {$job->id} must be SHIPPED before it can be DELIVERED."
                );
            }

            $job->update([
                'status' => 'DELIVERED',
                'completion_reference' => $completionReference,
                'delivered_at' => now(),
            ]);

            $job->events()->create([
                'event_type' => 'COURIER_DELIVERED',
                'from_status' => 'SHIPPED',
                'to_status' => 'DELIVERED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'completion_reference' => $completionReference,
                ],
            ]);

            $job->order()->update([
                'status' => 'COMPLETED',
            ]);

            return $job->fresh();
        });
    }
}
