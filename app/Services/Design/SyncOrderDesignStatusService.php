<?php

namespace App\Services\Design;

use App\Models\Order;

class SyncOrderDesignStatusService
{
    public function sync(Order $order): Order
    {
        $order->refresh()->load('designJobs');

        $statuses = $order->designJobs->pluck('status');

        if ($statuses->isEmpty()) {
            return $order;
        }

        $nextStatus = match (true) {
            $statuses->every(fn ($status) => $status === 'DESIGN_APPROVED')
                => 'DESIGN_APPROVED',

            $statuses->contains('CORRECTION_REQUESTED')
                => 'CORRECTION_REQUESTED',

            $statuses->every(fn ($status) => $status === 'DESIGN_READY')
                => 'DESIGN_READY',

            $statuses->contains('DESIGN_IN_PROGRESS')
                => 'DESIGN_IN_PROGRESS',

            default
                => 'READY_FOR_DESIGN',
        };

        if ($order->status !== $nextStatus) {
            $order->update(['status' => $nextStatus]);
        }

        return $order->fresh();
    }
}
