<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Packing\AssignPackingJobService;
use App\Services\Printing\AssignPrintJobService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use Illuminate\Support\Facades\DB;

class AssignOrderProductionStaffService
{
    public function __construct(
        private AssignPrintJobService $assignPrintJob,
        private AssignPackingJobService $assignPackingJob,
        private InitializePrintJobsForOrderService $initializePrintJobs,
    ) {}

    public function assignPrinting(Order $order, User $assignee, User $actor): Order
    {
        return DB::transaction(function () use ($order, $assignee, $actor): Order {
            $order->refresh();
            $previousUserId = $order->printing_assigned_user_id;

            $order->update(['printing_assigned_user_id' => $assignee->id]);

            if ($order->designJobs()->exists()
                && $order->designJobs()->where('status', '!=', 'DESIGN_APPROVED')->doesntExist()
                && in_array($order->status, ['DESIGN_APPROVED', 'BALANCE_PENDING', 'PAID'], true)) {
                $this->initializePrintJobs->initialize($order->fresh());
                $order->refresh();
            }

            foreach ($order->printJobs as $printJob) {
                $this->assignPrintJob->assign($printJob, $assignee, $actor);
            }

            $this->recordAssignment($order, 'PRINTING_STAFF_ASSIGNED', $previousUserId, $assignee, $actor);

            return $order->fresh(['printingAssignedUser', 'printJobs.assignedUser']);
        });
    }

    public function assignPackingAndFulfilment(Order $order, User $assignee, User $actor): Order
    {
        return DB::transaction(function () use ($order, $assignee, $actor): Order {
            $order->refresh();
            $previousUserId = $order->packing_assigned_user_id;

            $order->update(['packing_assigned_user_id' => $assignee->id]);

            if ($order->packingJob) {
                $this->assignPackingJob->assign($order->packingJob, $assignee, $actor);
            }

            $this->recordAssignment($order, 'PACKING_FULFILMENT_STAFF_ASSIGNED', $previousUserId, $assignee, $actor);

            return $order->fresh(['packingAssignedUser', 'packingJob.assignedUser']);
        });
    }

    private function recordAssignment(
        Order $order,
        string $eventType,
        ?int $previousUserId,
        User $assignee,
        User $actor
    ): void {
        $order->statusEvents()->create([
            'event_type' => $eventType,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'actor_user_id' => $actor->id,
            'source' => 'STAFF',
            'occurred_at' => now(),
            'metadata' => [
                'previous_assigned_user_id' => $previousUserId,
                'assigned_user_id' => $assignee->id,
            ],
        ]);
    }
}
