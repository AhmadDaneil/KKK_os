<?php

namespace App\Services\Design;

use App\Models\ArtworkVersion;
use App\Models\DesignJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateArtworkVersionService
{
    public function create(DesignJob $designJob, array $fileData, ?User $actor = null): ArtworkVersion
    {
        return DB::transaction(function () use ($designJob, $fileData, $actor) {
            $designJob->refresh();

            if ($designJob->status !== 'DESIGN_IN_PROGRESS') {
                throw new RuntimeException(
                    "Artwork can only be added while design job {$designJob->id} is DESIGN_IN_PROGRESS."
                );
            }

            $lastVersion = ArtworkVersion::where('design_job_id', $designJob->id)
                ->lockForUpdate()
                ->max('version_number');

            $versionNumber = ((int) $lastVersion) + 1;

            $artwork = ArtworkVersion::create([
                'design_job_id' => $designJob->id,
                'version_number' => $versionNumber,
                'storage_disk' => $fileData['storage_disk'] ?? 'local',
                'storage_path' => $fileData['storage_path'],
                'original_filename' => $fileData['original_filename'] ?? null,
                'mime_type' => $fileData['mime_type'] ?? null,
                'file_size_bytes' => $fileData['file_size_bytes'] ?? null,
                'checksum_sha256' => $fileData['checksum_sha256'] ?? null,
                'preview_storage_path' => $fileData['preview_storage_path'] ?? null,
                'internal_note' => $fileData['internal_note'] ?? null,
                'created_by_user_id' => $actor?->id,
            ]);

            $designJob->events()->create([
                'event_type' => 'ARTWORK_VERSION_CREATED',
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'artwork_version_id' => $artwork->id,
                    'version_number' => $versionNumber,
                    'storage_path' => $artwork->storage_path,
                ],
            ]);

            return $artwork;
        });
    }
}
