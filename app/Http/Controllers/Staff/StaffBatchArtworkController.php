<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Services\Design\CreateArtworkVersionService;
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
        CreateArtworkVersionService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'artworks' => ['required', 'array', 'min:2'],
            'artworks.*' => ['required', 'array:source_artwork,customer_preview,internal_note'],
            'artworks.*.source_artwork' => ['required', 'file', 'mimes:psd,pdf', 'max:102400'],
            'artworks.*.customer_preview' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
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
            DB::transaction(function () use ($validated, $jobs, $request, $service, &$storedPaths): void {
                foreach ($validated['artworks'] as $jobId => $files) {
                    $this->storeArtworkVersion(
                        $jobs->get((int) $jobId),
                        $files['source_artwork'],
                        $files['customer_preview'],
                        $files['internal_note'] ?? null,
                        $request,
                        $service,
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
        UploadedFile $source,
        UploadedFile $preview,
        ?string $internalNote,
        Request $request,
        CreateArtworkVersionService $service,
        array &$storedPaths
    ): void {
        $disk = Storage::disk('local');
        $directory = implode('/', [
            'artworks',
            (string) $job->order_id,
            $this->safeSide($job->side),
            (string) Str::uuid(),
        ]);

        $sourcePath = $disk->putFileAs(
            $directory,
            $source,
            'source.'.strtolower($source->extension())
        );

        if ($sourcePath === false) {
            throw new RuntimeException('Unable to store source artwork.');
        }

        $storedPaths[] = $sourcePath;

        $previewPath = $disk->putFileAs(
            $directory,
            $preview,
            'preview.'.strtolower($preview->extension())
        );

        if ($previewPath === false) {
            throw new RuntimeException('Unable to store customer preview.');
        }

        $storedPaths[] = $previewPath;
        $checksum = hash_file('sha256', $source->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate artwork checksum.');
        }

        $service->create($job, [
            'storage_disk' => 'local',
            'storage_path' => $sourcePath,
            'original_filename' => $source->getClientOriginalName(),
            'mime_type' => $source->getMimeType() ?: 'application/octet-stream',
            'file_size_bytes' => $source->getSize(),
            'checksum_sha256' => $checksum,
            'preview_storage_path' => $previewPath,
            'internal_note' => $internalNote,
        ], $request->user());
    }

    private function safeSide(?string $side): string
    {
        $side = strtoupper(trim((string) $side));

        return in_array($side, ['LELAKI', 'PEREMPUAN'], true)
            ? $side
            : 'UNKNOWN';
    }
}
