<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\ResumeDesignAfterCorrectionService;
use App\Services\Design\StartDesignJobService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Design\WatermarkArtworkPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StaffDesignWorkflowController extends Controller
{
    public function start(
        Request $request,
        DesignJob $designJob,
        StartDesignJobService $service,
        SyncOrderDesignStatusService $sync
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        try {
            $service->start(
                $designJob,
                $request->user()
            );
            $sync->sync($designJob->order);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'design_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Design work started successfully.'
        );
    }

    public function resumeCorrection(
        Request $request,
        DesignJob $designJob,
        ResumeDesignAfterCorrectionService $service,
        SyncOrderDesignStatusService $sync
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        try {
            $service->resume(
                $designJob,
                $request->user()
            );
            $sync->sync($designJob->order);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'design_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Correction work resumed successfully.'
        );
    }

    public function markReady(
        Request $request,
        DesignJob $designJob,
        MarkDesignReadyService $service,
        SyncOrderDesignStatusService $sync
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        try {
            $service->markReady(
                $designJob,
                $request->user()
            );
            $sync->sync($designJob->order);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'design_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Artwork marked ready for customer review.'
        );
    }

    public function uploadArtwork(
        Request $request,
        DesignJob $designJob,
        CreateArtworkVersionService $service,
        WatermarkArtworkPreviewService $watermark
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        $this->normalizeArtworkFiles($request);

        $validated = $request->validate([
            'source_artwork' => ['required', 'array', 'min:1', 'max:20'],
            'source_artwork.*' => ['required', 'file', 'mimes:psd,pdf', 'max:102400'],
            'customer_preview' => ['required', 'array', 'min:1', 'max:20'],
            'customer_preview.*' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:20480'],
            'internal_note' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
         * Reject invalid lifecycle state before writing anything
         * to private storage.
         */
        $designJob->refresh();

        if ($designJob->status !== 'DESIGN_IN_PROGRESS') {
            return back()->withErrors([
                'design_job' => "Artwork can only be added while design job {$designJob->id} is DESIGN_IN_PROGRESS.",
            ]);
        }

        $diskName = 'local';
        $disk = Storage::disk($diskName);

        /*
         * A UUID directory prevents overwrite/race conditions.
         * Version number remains controlled exclusively by
         * CreateArtworkVersionService.
         */
        $directory = implode('/', [
            'artworks',
            (string) $designJob->order_id,
            $this->safeSide($designJob->side),
            (string) Str::uuid(),
        ]);

        $storedPaths = [];

        try {
            $sourceFiles = $this->storeFileCollection(
                $validated['source_artwork'],
                $directory,
                'source',
                $storedPaths
            );
            $previewFiles = $this->storeFileCollection(
                $validated['customer_preview'],
                $directory,
                'preview',
                $storedPaths,
                $watermark
            );
            $primarySource = $sourceFiles[0];
            $primaryPreview = $previewFiles[0];

            $artwork = $service->create(
                $designJob,
                [
                    'storage_disk' => $diskName,
                    'storage_path' => $primarySource['path'],
                    'original_filename' => $primarySource['original_name'],
                    'mime_type' => $primarySource['mime_type'],
                    'file_size_bytes' => $primarySource['size'],
                    'checksum_sha256' => $primarySource['checksum_sha256'],
                    'source_files' => $sourceFiles,
                    'preview_storage_path' => $primaryPreview['path'],
                    'preview_files' => $previewFiles,
                    'internal_note' => $validated['internal_note'] ?? null,
                ],
                $request->user()
            );
        } catch (Throwable $exception) {
            /*
             * Filesystem is outside the database transaction.
             * Remove anything written by this request if DB/service
             * creation fails.
             */
            $disk->delete($storedPaths);

            if ($exception instanceof RuntimeException) {
                return back()->withErrors([
                    'design_job' => $exception->getMessage(),
                ]);
            }

            report($exception);

            return back()->withErrors([
                'design_job' => 'Artwork could not be uploaded. Please try again.',
            ]);
        }

        return back()->with(
            'status',
            count($validated['source_artwork']) === 1 && count($validated['customer_preview']) === 1
                ? "Artwork version {$artwork->version_number} uploaded successfully."
                : "Artwork version {$artwork->version_number} uploaded successfully with "
                    .count($validated['source_artwork']).' source file(s) and '
                    .count($validated['customer_preview']).' preview file(s).'
        );
    }

    private function normalizeArtworkFiles(Request $request): void
    {
        foreach (['source_artwork', 'customer_preview'] as $field) {
            $file = $request->files->get($field);

            if ($file instanceof UploadedFile) {
                $request->files->set($field, [$file]);
            }
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string>  $storedPaths
     * @return array<int, array<string, int|string>>
     */
    private function storeFileCollection(
        array $files,
        string $directory,
        string $prefix,
        array &$storedPaths,
        ?WatermarkArtworkPreviewService $watermark = null
    ): array {
        $disk = Storage::disk('local');
        $storedFiles = [];

        foreach (array_values($files) as $index => $file) {
            $filename = $prefix.($index === 0 ? '' : '-'.($index + 1))
                .'.'.strtolower($file->extension());
            $path = $disk->putFileAs(
                $directory,
                $file,
                $filename
            );

            if ($path === false) {
                throw new RuntimeException("Unable to store {$prefix} artwork file.");
            }

            $checksum = hash_file('sha256', $file->getRealPath());

            if ($checksum === false) {
                throw new RuntimeException("Unable to calculate {$prefix} artwork checksum.");
            }

            $storedPaths[] = $path;
            $watermarkedPath = null;

            if ($watermark !== null) {
                $watermarkedPath = $watermark->create(
                    $path,
                    $directory.'/preview-watermarked-'.($index + 1).'.jpg'
                );

                if ($watermarkedPath !== null) {
                    $storedPaths[] = $watermarkedPath;
                }
            }

            $storedFiles[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'watermarked_path' => $watermarkedPath,
                'watermark_version' => $watermarkedPath === null ? null : 2,
            ];
        }

        return $storedFiles;
    }

    private function authorizeAssignedDesigner(
        Request $request,
        DesignJob $designJob
    ): void {
        abort_unless(
            $designJob->assigned_user_id === $request->user()->id,
            404
        );
    }

    private function safeSide(?string $side): string
    {
        $side = strtoupper(trim((string) $side));

        return in_array(
            $side,
            ['LELAKI', 'PEREMPUAN'],
            true
        )
            ? $side
            : 'UNKNOWN';
    }
}
