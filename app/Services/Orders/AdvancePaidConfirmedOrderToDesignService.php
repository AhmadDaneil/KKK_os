<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use Illuminate\Support\Facades\DB;

class AdvancePaidConfirmedOrderToDesignService
{
    public function __construct(
        private GenerateMergeJobsForOrderService $mergeJobs,
        private InitializeDesignJobsForOrderService $designJobs,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function advance(
        Order $order,
        string $eventType = 'PAID_DETAILS_CONFIRMED',
        ?int $actorUserId = null,
        string $source = 'SYSTEM',
        array $metadata = [],
    ): Order {
        return DB::transaction(function () use ($order, $eventType, $actorUserId, $source, $metadata): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->isTerminalOperationalStatus()
                || $lockedOrder->status !== 'DETAILS_CONFIRMED'
                || $lockedOrder->booking_payment_status !== 'PAID') {
                return $lockedOrder;
            }

            $this->mergeJobs->generate($lockedOrder);
            $this->designJobs->initialize($lockedOrder);
            $lockedOrder->update(['status' => 'READY_FOR_DESIGN']);
            $lockedOrder->statusEvents()->create([
                'event_type' => $eventType,
                'from_status' => 'DETAILS_CONFIRMED',
                'to_status' => 'READY_FOR_DESIGN',
                'actor_user_id' => $actorUserId,
                'source' => $source,
                'occurred_at' => now(),
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            return $lockedOrder->fresh();
        }, 3);
    }
}
