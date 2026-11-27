<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'Production started successfully.'
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
            'Production completed successfully.'
        );
    }

    public function showProgressFile(
        Request $request,
        PrintJob $printJob,
        int $file
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user->isOperationManagement()
                || ($user->hasStaffRole(User::ROLE_PRODUCTION)
                    && $printJob->assigned_user_id === $user->id),
            404
        );

        $progressFile = data_get($printJob->progress_files ?? [], $file);
        $path = data_get($progressFile, 'path');

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            data_get($progressFile, 'original_name', 'printing-progress'),
            ['Content-Type' => data_get($progressFile, 'mime_type', 'application/octet-stream')]
        );
    }

    private function authorizeAssignedPrintingStaff(
        Request $request,
        PrintJob $printJob
    ): void {
        abort_unless(
            $request->user()->hasStaffRole(User::ROLE_PRODUCTION)
                && $printJob->assigned_user_id === $request->user()->id,
            404
        );
    }
}
