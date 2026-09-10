<?php

namespace App\Services\Printing;

use App\Models\Order;

class SyncOrderPrintStatusService
{
    public function sync(Order $order): Order
    {
        $order->refresh()->load('printJobs');

        if ($order->printJobs->isEmpty()) {
            return $order;
        }

        $statuses = $order->printJobs->pluck('status');

        $nextStatus = match (true) {
            $statuses->every(fn ($status) => $status === 'PRINTED')
                => 'PRINTED',

            $statuses->contains('PRINTING')
                => 'PRINTING',

            default
                => 'READY_FOR_PRINT',
        };

        if ($order->status !== $nextStatus) {
            $order->update([
                'status' => $nextStatus,
            ]);
        }

        return $order->fresh();
    }
}
