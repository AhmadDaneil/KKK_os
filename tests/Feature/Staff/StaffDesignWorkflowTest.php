<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\ResumeDesignAfterCorrectionService;
use App\Services\Design\StartDesignJobService;
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
        StartDesignJobService $service
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        try {
            $service->start(
                $designJob,
                $request->user()
            );
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
        ResumeDesignAfterCorrectionService $service
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        try {
            $service->resume(
                $designJob,
                $request->user()
            );
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

    public function uploadArtwork(
        Request $request,
        DesignJob $designJob,
        CreateArtworkVersionService $service
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $designJob);

        $validated = $request->validate([
            'source_artwork' => [
                'required',
                'file',
                'mimes:psd,pdf',
                'max:102400',
            ],
            'customer_preview' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:20480',
            ],
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
                'design_job' =>
                    "Artwork can only be added while design job {$designJob->id} is DESIGN_IN_PROGRESS.",
            ]);
        }

        /** @var UploadedFile $source */
        $source = $validated['source_artwork'];

        /** @var UploadedFile $preview */
        $preview = $validated['customer_preview'];

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

        $sourceExtension = strtolower(
            $source->getClientOriginalExtension()
        );

        $previewExtension = strtolower(
            $preview->getClientOriginalExtension()
        );

        $sourcePath = $directory . '/source.' . $sourceExtension;
        $previewPath = $directory . '/preview.' . $previewExtension;

        $storedSource = false;
        $storedPreview = false;

        try {
            $sourceStream = fopen(
                $source->getRealPath(),
                'rb'
            );

            if ($sourceStream === false) {
                throw new RuntimeException(
                    'Unable to read uploaded source artwork.'
                );
            }

            try {
                $storedSource = $disk->put(
                    $sourcePath,
                    $sourceStream
                );
            } finally {
                if (is_resource($sourceStream)) {
                    fclose($sourceStream);
                }
            }

            if (! $storedSource) {
                throw new RuntimeException(
                    'Unable to store source artwork.'
                );
            }

            $previewStream = fopen(
                $preview->getRealPath(),
                'rb'
            );

            if ($previewStream === false) {
                throw new RuntimeException(
                    'Unable to read uploaded customer preview.'
                );
            }

            try {
                $storedPreview = $disk->put(
                    $previewPath,
                    $previewStream
                );
            } finally {
                if (is_resource($previewStream)) {
                    fclose($previewStream);
                }
            }

            if (! $storedPreview) {
                throw new RuntimeException(
                    'Unable to store customer preview.'
                );
            }

            $checksum = hash_file(
                'sha256',
                $source->getRealPath()
            );

            if ($checksum === false) {
                throw new RuntimeException(
                    'Unable to calculate artwork checksum.'
                );
            }

            $artwork = $service->create(
                $designJob,
                [
                    'storage_disk' => $diskName,
                    'storage_path' => $sourcePath,
                    'original_filename' =>
                        $source->getClientOriginalName(),
                    'mime_type' =>
                        $source->getMimeType()
                        ?: 'application/octet-stream',
                    'file_size_bytes' =>
                        $source->getSize(),
                    'checksum_sha256' => $checksum,
                    'preview_storage_path' => $previewPath,
                    'internal_note' =>
                        $validated['internal_note'] ?? null,
                ],
                $request->user()
            );
        } catch (Throwable $exception) {
            /*
             * Filesystem is outside the database transaction.
             * Remove anything written by this request if DB/service
             * creation fails.
             */
            if ($storedPreview) {
                $disk->delete($previewPath);
            }

            if ($storedSource) {
                $disk->delete($sourcePath);
            }

            if ($exception instanceof RuntimeException) {
                return back()->withErrors([
                    'design_job' => $exception->getMessage(),
                ]);
            }

            report($exception);

            return back()->withErrors([
                'design_job' =>
                    'Artwork could not be uploaded. Please try again.',
            ]);
        }

        return back()->with(
            'status',
            "Artwork version {$artwork->version_number} uploaded successfully."
        );
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