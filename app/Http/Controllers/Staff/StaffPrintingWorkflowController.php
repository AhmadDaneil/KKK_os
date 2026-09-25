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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function uploadProgress(
        Request $request,
        PrintJob $printJob
    ): RedirectResponse {
        $this->authorizeAssignedPrintingStaff($request, $printJob);

        if ($printJob->status !== 'PRINTING') {
            return back()->withErrors([
                'print_job' => 'Progress files can only be uploaded while this job is PRINTING.',
            ]);
        }

        $validated = $request->validate([
            'progress_files' => ['required', 'array', 'min:1', 'max:10'],
            'progress_files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
        ]);

        $disk = Storage::disk('local');
        $directory = 'printing-progress/'.$printJob->id.'/'.Str::uuid();
        $storedPaths = [];

        try {
            $newFiles = collect($validated['progress_files'])
                ->values()
                ->map(function (UploadedFile $file, int $index) use ($disk, $directory, &$storedPaths, $request): array {
                    $path = $disk->putFileAs(
                        $directory,
                        $file,
                        'progress-'.($index + 1).'.'.strtolower($file->extension())
                    );

                    if ($path === false) {
                        throw new RuntimeException('Unable to store the printing progress file.');
                    }

                    $storedPaths[] = $path;

                    return [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                        'uploaded_at' => now()->toIso8601String(),
                        'uploaded_by_user_id' => $request->user()->id,
                    ];
                })
                ->all();

            $printJob->update([
                'progress_files' => array_merge($printJob->progress_files ?? [], $newFiles),
                'progress_updated_at' => now(),
            ]);

            $printJob->events()->create([
                'event_type' => 'PRINT_PROGRESS_UPLOADED',
                'from_status' => 'PRINTING',
                'to_status' => 'PRINTING',
                'actor_user_id' => $request->user()->id,
                'metadata' => ['file_count' => count($newFiles)],
                'occurred_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $disk->delete($storedPaths);

            return back()->withErrors([
                'print_job' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Printing progress uploaded successfully.');
    }

    public function showProgressFile(
        Request $request,
        PrintJob $printJob,
        int $file
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user->isOperationManagement()
                || ($user->hasStaffRole(User::ROLE_PRINTING)
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
            $request->user()->hasStaffRole(User::ROLE_PRINTING)
                && $printJob->assigned_user_id === $request->user()->id,
            404
        );
    }
}
