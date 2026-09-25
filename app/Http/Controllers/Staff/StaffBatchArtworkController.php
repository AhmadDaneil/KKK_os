<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\WatermarkArtworkPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StaffBatchArtworkController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        CreateArtworkVersionService $service,
        WatermarkArtworkPreviewService $watermark
    ): RedirectResponse {
        $this->normalizeArtworkFiles($request);

        $validated = $request->validate([
            'artworks' => ['required', 'array', 'min:2'],
            'artworks.*' => ['required', 'array:source_artwork,customer_preview,internal_note'],
            'artworks.*.source_artwork' => ['required', 'array', 'min:1', 'max:20'],
            'artworks.*.source_artwork.*' => ['required', 'file', 'mimes:psd,pdf', 'max:102400'],
            'artworks.*.customer_preview' => ['required', 'array', 'min:1', 'max:20'],
            'artworks.*.customer_preview.*' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:20480'],
            'artworks.*.internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $jobIds = collect(array_keys($validated['artworks']))
            ->map(fn (string|int $id): int => (int) $id)
            ->unique()
            ->values();

        $jobs = $order->designJobs()
            ->whereIn('id', $jobIds)
            ->get()
            ->keyBy('id');

        abort_unless($jobs->count() === $jobIds->count(), 404);

        foreach ($jobs as $job) {
            abort_unless($job->assigned_user_id === $request->user()->id, 404);

            if ($job->status !== 'DESIGN_IN_PROGRESS') {
                return back()->withErrors([
                    'artworks' => "Artwork can only be uploaded while {$job->side} design is in progress.",
                ]);
            }
        }

        $storedPaths = [];

        try {
            DB::transaction(function () use ($validated, $jobs, $request, $service, $watermark, &$storedPaths): void {
                foreach ($validated['artworks'] as $jobId => $files) {
                    $this->storeArtworkVersion(
                        $jobs->get((int) $jobId),
                        $files['source_artwork'],
                        $files['customer_preview'],
                        $files['internal_note'] ?? null,
                        $request,
                        $service,
                        $watermark,
                        $storedPaths
                    );
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            if ($exception instanceof RuntimeException) {
                return back()->withErrors([
                    'artworks' => $exception->getMessage(),
                ]);
            }

            report($exception);

            return back()->withErrors([
                'artworks' => 'Artwork could not be uploaded. Please try again.',
            ]);
        }

        return back()->with(
            'status',
            $jobs->count().' artwork files uploaded successfully.'
        );
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    private function storeArtworkVersion(
        DesignJob $job,
        array $sources,
        array $previews,
        ?string $internalNote,
        Request $request,
        CreateArtworkVersionService $service,
        WatermarkArtworkPreviewService $watermark,
        array &$storedPaths
    ): void {
        $disk = Storage::disk('local');
        $directory = implode('/', [
            'artworks',
            (string) $job->order_id,
            $this->safeSide($job->side),
            (string) Str::uuid(),
        ]);

        $sourceFiles = $this->storeFileCollection($sources, $directory, 'source', $storedPaths);
        $previewFiles = $this->storeFileCollection($previews, $directory, 'preview', $storedPaths, $watermark);
        $primarySource = $sourceFiles[0];
        $primaryPreview = $previewFiles[0];

        $service->create($job, [
            'storage_disk' => 'local',
            'storage_path' => $primarySource['path'],
            'original_filename' => $primarySource['original_name'],
            'mime_type' => $primarySource['mime_type'],
            'file_size_bytes' => $primarySource['size'],
            'checksum_sha256' => $primarySource['checksum_sha256'],
            'source_files' => $sourceFiles,
            'preview_storage_path' => $primaryPreview['path'],
            'preview_files' => $previewFiles,
            'internal_note' => $internalNote,
        ], $request->user());
    }

    private function normalizeArtworkFiles(Request $request): void
    {
        $artworks = $request->files->get('artworks', []);

        foreach ($artworks as $jobId => $files) {
            foreach (['source_artwork', 'customer_preview'] as $field) {
                if (($files[$field] ?? null) instanceof UploadedFile) {
                    $artworks[$jobId][$field] = [$files[$field]];
                }
            }
        }

        $request->files->set('artworks', $artworks);
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

    private function safeSide(?string $side): string
    {
        $side = strtoupper(trim((string) $side));

        return in_array($side, ['LELAKI', 'PEREMPUAN'], true)
            ? $side
            : 'UNKNOWN';
    }
}
