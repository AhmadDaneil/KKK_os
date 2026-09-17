<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PackingJob;
use App\Models\PackingJobItem;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StaffPackingWorkflowController extends Controller
{
    public function start(
        Request $request,
        PackingJob $packingJob,
        StartPackingService $service
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);

        try {
            $service->start(
                $packingJob,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Packing started successfully.'
        );
    }

    public function verifyItem(
        Request $request,
        PackingJob $packingJob,
        PackingJobItem $packingItem,
        VerifyPackingItemService $service
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);

        abort_unless(
            $packingItem->packing_job_id === $packingJob->id,
            404
        );

        try {
            $service->verify(
                $packingItem,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Packing item verified successfully.'
        );
    }

    public function markPacked(
        Request $request,
        PackingJob $packingJob,
        MarkPackingJobPackedService $service
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);

        try {
            $service->markPacked(
                $packingJob,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Packing completed successfully.'
        );
    }

    private function authorizeAssignedPackingStaff(
        Request $request,
        PackingJob $packingJob
    ): void {
        abort_unless(
            $packingJob->assigned_user_id === $request->user()->id,
            404
        );
    }
}