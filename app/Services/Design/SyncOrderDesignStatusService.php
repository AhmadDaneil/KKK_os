<?php

namespace App\Services\Design;

use App\Models\Order;

class SyncOrderDesignStatusService
{
    public function sync(Order $order): Order
    {
        $order->refresh()->load(['designJobs', 'pricingSnapshot']);

        if ($order->isTerminalOperationalStatus()) {
            return $order;
        }

        $statuses = $order->designJobs->pluck('status');

        if ($statuses->isEmpty()) {
            return $order;
        }

        $hasNoOutstandingBalance = $order->pricingSnapshot !== null
            && (float) $order->pricingSnapshot->outstanding_amount <= 0;

        $newStatus = match (true) {
            $statuses->every(fn ($status) => $status === 'DESIGN_APPROVED')
                => $hasNoOutstandingBalance ? 'PAID' : 'DESIGN_APPROVED',

    $statuses->contains('CORRECTION_REQUESTED')
        => 'CORRECTION_REQUESTED',

    $statuses->contains('DESIGN_IN_PROGRESS')
        => 'DESIGN_IN_PROGRESS',

    $statuses->contains('DESIGN_READY')
        => 'DESIGN_READY',

    default
        => 'READY_FOR_DESIGN',
};

        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
        }

        return $order->fresh();
    }
}
