<?php

namespace App\Services\Fulfilment;

use App\Models\FulfilmentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkPickupCollectedService
{
    public function collect(
        FulfilmentJob $job,
        ?string $completionReference = null,
        ?User $actor = null,
    ): FulfilmentJob {
        return DB::transaction(function () use ($job, $completionReference, $actor) {
            $job->refresh()->load('order');

            if ($job->order->isTerminalOperationalStatus()) {
                throw new RuntimeException(
                    "Order {$job->order->order_id} is {$job->order->status}; fulfilment cannot continue."
                );
            }

            if ($job->method !== 'PICKUP') {
                throw new RuntimeException(
                    "Fulfilment job {$job->id} is not a PICKUP job."
                );
            }

            if ($job->status === 'COLLECTED') {
                return $job;
            }

            if ($job->status !== 'READY') {
                throw new RuntimeException(
                    "Pickup job {$job->id} cannot be collected from status {$job->status}."
                );
            }

            $job->update([
                'status' => 'COLLECTED',
                'completion_reference' => $completionReference,
                'collected_at' => now(),
            ]);

            $job->events()->create([
                'event_type' => 'PICKUP_COLLECTED',
                'from_status' => 'READY',
                'to_status' => 'COLLECTED',
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
