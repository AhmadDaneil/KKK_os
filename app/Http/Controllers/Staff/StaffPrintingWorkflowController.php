<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use App\Services\Packing\InitializePackingJobForOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StaffPrintingWorkflowController extends Controller
{
    public function start(
        Request $request,
        PrintJob $printJob,
        StartPrintingService $service,
        SyncOrderPrintStatusService $syncService
    ): RedirectResponse {
        $this->authorizeAssignedPrintingStaff($request, $printJob);

        try {
            $service->start(
                $printJob,
                $request->user()
            );

            $syncService->sync(
                $printJob->order
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'print_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Printing started successfully.'
        );
    }

    public function markPrinted(
        Request $request,
        PrintJob $printJob,
        MarkPrintJobPrintedService $service,
        SyncOrderPrintStatusService $syncService,
        InitializePackingJobForOrderService $initializePacking
    ): RedirectResponse {
        $this->authorizeAssignedPrintingStaff($request, $printJob);

        try {
            $service->markPrinted(
                $printJob,
                $request->user()
            );

            $order = $syncService->sync(
                $printJob->order
            );

            if ($order->status === 'PRINTED') {
                $initializePacking->initialize($order);
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'print_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Printing completed successfully.'
        );
    }

    private function authorizeAssignedPrintingStaff(
        Request $request,
        PrintJob $printJob
    ): void {
        abort_unless(
            $printJob->assigned_user_id === $request->user()->id,
            404
        );
    }
}
