<?php

namespace App\Services\Packing;

use App\Models\Order;
use App\Models\PackingJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartPackingService
{
    public function __construct(private BuildPackingChecklistService $checklistBuilder) {}

    /** @param list<array{key: string, description: string, quantity: int, side: ?string}> $checklist */
    public function start(PackingJob $packingJob, ?User $actor = null, array $checklist = []): PackingJob
    {
        return DB::transaction(function () use ($packingJob, $actor, $checklist) {
            Order::query()->whereKey($packingJob->order_id)->lockForUpdate()->firstOrFail();
            $packingJob->refresh();

            if (! in_array($packingJob->status, ['READY_FOR_PACKING', 'PACKING'], true)) {
                throw new RuntimeException(
                    "Packing job {$packingJob->id} cannot start from status {$packingJob->status}."
                );
            }

            if ($packingJob->status === 'PACKING') {
                return $packingJob;
            }

            $order = $packingJob->order()->with('pricingSnapshot')->firstOrFail();
            if ($order->items()->where('item_type', 'ADDON')->exists() && (float) ($order->pricingSnapshot?->outstanding_amount ?? 0) > 0) {
                throw new RuntimeException('The order has an unpaid Add-On balance. Confirm full payment before starting packing.');
            }
            if ($checklist !== [] && array_column($checklist, 'key') !== array_column($this->checklistBuilder->build($order), 'key')) {
                throw new RuntimeException('The order items have changed. Refresh and check every package item again.');
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
                'metadata' => ['package_checklist' => $checklist],
            ]);

            return $packingJob->fresh();
        });
    }
}
