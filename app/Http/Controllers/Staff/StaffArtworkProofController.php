<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffArtworkProofController extends Controller
{
    public function show(Request $request, DesignJob $designJob): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canViewAllProgressUpdates(), 404);

        $artwork = $designJob->artworkVersions()
            ->latest('version_number')
            ->firstOrFail();
        $previewFiles = $artwork->preview_files ?: array_filter([
            $artwork->preview_storage_path ? ['path' => $artwork->preview_storage_path] : null,
        ]);
        $previewFile = data_get($previewFiles, $request->integer('file', 0));
        $path = data_get($previewFile, 'watermarked_path') ?: data_get($previewFile, 'path');
        $disk = $artwork->storage_disk ?: 'local';

        abort_unless(filled($path) && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response(
            $path,
            basename($path),
            [
                'Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
