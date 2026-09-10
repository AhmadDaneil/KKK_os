<?php

namespace App\Services\Fulfilment;

use App\Models\FulfilmentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkCourierShippedService
{
    public function ship(
        FulfilmentJob $job,
        ?string $courierProvider = null,
        ?string $trackingNumber = null,
        ?User $actor = null,
    ): FulfilmentJob {
        return DB::transaction(function () use ($job, $courierProvider, $trackingNumber, $actor) {
            $job->refresh();

            if ($job->method !== 'COURIER') {
                throw new RuntimeException(
                    "Fulfilment job {$job->id} is not a COURIER job."
                );
            }

            if ($job->status === 'SHIPPED') {
                return $job;
            }

            if ($job->status !== 'READY') {
                throw new RuntimeException(
                    "Courier job {$job->id} cannot be shipped from status {$job->status}."
                );
            }

            $job->update([
                'status' => 'SHIPPED',
                'courier_provider' => $courierProvider,
                'tracking_number' => $trackingNumber,
                'shipped_at' => now(),
            ]);

            $job->events()->create([
                'event_type' => 'COURIER_SHIPPED',
                'from_status' => 'READY',
                'to_status' => 'SHIPPED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'courier_provider' => $courierProvider,
                    'tracking_number' => $trackingNumber,
                ],
            ]);

            return $job->fresh();
        });
    }
}
