<?php

namespace App\Services\Fulfilment;

use App\Models\FulfilmentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CompleteCourierFulfilmentService
{
    public function complete(
        FulfilmentJob $job,
        ?string $courierProvider = null,
        ?string $trackingNumber = null,
        ?User $actor = null,
    ): FulfilmentJob {
        return DB::transaction(function () use (
            $job,
            $courierProvider,
            $trackingNumber,
            $actor
        ) {
            $job->refresh()->load(['order', 'packingJob']);

            if ($job->order->isTerminalOperationalStatus()) {
                if (
                    $job->order->status === 'COMPLETED'
                    && $job->status === 'COMPLETED'
                ) {
                    return $job;
                }

                throw new RuntimeException(
                    "Order {$job->order->order_id} is {$job->order->status}; fulfilment cannot continue."
                );
            }

            if ($job->method !== 'COURIER') {
                throw new RuntimeException(
                    "Fulfilment job {$job->id} is not a COURIER job."
                );
            }

            if ($job->status === 'COMPLETED') {
                return $job;
            }

            if ($job->status !== 'READY') {
                throw new RuntimeException(
                    "Courier job {$job->id} cannot be completed from status {$job->status}."
                );
            }

            if (! filled($courierProvider) || ! filled($trackingNumber)) {
                throw new RuntimeException(
                    'Courier provider and tracking number are required before courier completion.'
                );
            }

            if (
                ! $job->packingJob
                || $job->packingJob->status !== 'PACKED'
            ) {
                throw new RuntimeException(
                    "Courier job {$job->id} requires a completed packing job."
                );
            }

            if (
                ! filled($job->packingJob->proof_storage_path)
                || ! Storage::disk('local')->exists(
                    $job->packingJob->proof_storage_path
                )
            ) {
                throw new RuntimeException(
                    "Courier job {$job->id} requires packing proof before completion."
                );
            }

            $completedAt = now();

            $job->update([
                'status' => 'COMPLETED',
                'courier_provider' => trim($courierProvider),
                'tracking_number' => trim($trackingNumber),

                // Existing column retained as the physical handoff timestamp.
                'shipped_at' => $completedAt,
            ]);

            $job->events()->create([
                'event_type' => 'COURIER_COMPLETED',
                'from_status' => 'READY',
                'to_status' => 'COMPLETED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => $completedAt,
                'metadata' => [
                    'courier_provider' => trim($courierProvider),
                    'tracking_number' => trim($trackingNumber),
                    'packing_proof_storage_path' =>
                        $job->packingJob->proof_storage_path,
                ],
            ]);

            $job->order()->update([
                'status' => 'COMPLETED',
            ]);

            return $job->fresh(['events']);
        });
    }
}