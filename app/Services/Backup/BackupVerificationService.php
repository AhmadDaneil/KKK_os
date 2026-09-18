<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Throwable;

final class BackupVerificationService
{
    public function __construct(private FilesystemManager $filesystems) {}

    /**
     * @return array{
     *     backup_id: string,
     *     created_at: string,
     *     artifact_path: string,
     *     sha256: string,
     *     size_bytes: int,
     *     disks: list<string>
     * }
     */
    public function verify(?string $backupId = null): array
    {
        $disks = $this->configuredDisks();
        $manifestsByDisk = [];

        foreach ($disks as $diskName) {
            $manifestsByDisk[$diskName] = $this->readManifests($diskName);
        }

        $backupId ??= $this->latestCommonBackupId(
            $manifestsByDisk[$disks[0]],
            $manifestsByDisk[$disks[1]],
        );

        $reference = null;

        foreach ($disks as $diskName) {
            $manifest = $manifestsByDisk[$diskName][$backupId] ?? null;

            if ($manifest === null) {
                throw new RuntimeException("Backup [{$backupId}] is missing from disk [{$diskName}].");
            }

            $this->verifyArtifact($diskName, $manifest);

            if ($reference !== null && $this->comparableManifest($manifest) !== $this->comparableManifest($reference)) {
                throw new RuntimeException("Backup [{$backupId}] has different manifests across the two locations.");
            }

            $reference = $manifest;
        }

        return [
            'backup_id' => $backupId,
            'created_at' => (string) $reference['created_at'],
            'artifact_path' => (string) $reference['artifact_path'],
            'sha256' => (string) $reference['sha256'],
            'size_bytes' => (int) $reference['size_bytes'],
            'disks' => $disks,
        ];
    }

    /**
     * @return list<string>
     */
    private function configuredDisks(): array
    {
        $disks = array_values(array_filter(
            (array) config('backup.database.disks', []),
            static fn (mixed $disk): bool => is_string($disk) && trim($disk) !== '',
        ));
        $disks = array_map(static fn (string $disk): string => trim($disk), $disks);

        if (count($disks) !== 2 || count(array_unique($disks)) !== 2) {
            throw new RuntimeException('Database backup verification requires exactly two distinct filesystem disks.');
        }

        return $disks;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifests(string $diskName): array
    {
        $disk = $this->filesystems->disk($diskName);
        $root = trim((string) config('backup.database.path', 'database'), '/');
        $manifests = [];

        foreach ($disk->allFiles($root) as $path) {
            if (! str_ends_with($path, '.manifest.json')) {
                continue;
            }

            try {
                $manifest = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Backup manifest [{$path}] on disk [{$diskName}] is not valid JSON.",
                    previous: $exception,
                );
            }

            $this->assertManifest($manifest, $path, $diskName, $root);

            if (isset($manifests[$manifest['backup_id']])) {
                throw new RuntimeException("Duplicate backup ID [{$manifest['backup_id']}] exists on disk [{$diskName}].");
            }

            $manifests[$manifest['backup_id']] = $manifest;
        }

        return $manifests;
    }

    private function assertManifest(mixed $manifest, string $path, string $diskName, string $root): void
    {
        if (! is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 'kkk-os-database-backup-v1'
            || ! is_string($manifest['backup_id'] ?? null)
            || ! is_string($manifest['created_at'] ?? null)
            || ! is_string($manifest['artifact_path'] ?? null)
            || ! is_string($manifest['sha256'] ?? null)
            || ! is_int($manifest['size_bytes'] ?? null)
            || ($manifest['manifest_path'] ?? null) !== $path
            || ! str_starts_with($manifest['artifact_path'], $root.'/')) {
            throw new RuntimeException("Backup manifest [{$path}] on disk [{$diskName}] does not match the V1 contract.");
        }

        try {
            CarbonImmutable::parse($manifest['created_at']);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Backup manifest [{$path}] on disk [{$diskName}] has an invalid created_at value.",
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function latestCommonBackupId(array $first, array $second): string
    {
        $commonBackupIds = array_intersect(array_keys($first), array_keys($second));

        if ($commonBackupIds === []) {
            throw new RuntimeException('No database backup exists in both configured locations.');
        }

        usort($commonBackupIds, fn (string $left, string $right): int => CarbonImmutable::parse($first[$right]['created_at'])
            <=> CarbonImmutable::parse($first[$left]['created_at']));

        return $commonBackupIds[0];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function verifyArtifact(string $diskName, array $manifest): void
    {
        $disk = $this->filesystems->disk($diskName);
        $artifactPath = (string) $manifest['artifact_path'];

        if (! $disk->exists($artifactPath)) {
            throw new RuntimeException("Backup artifact [{$artifactPath}] is missing from disk [{$diskName}].");
        }

        if ($disk->size($artifactPath) !== (int) $manifest['size_bytes']) {
            throw new RuntimeException("Backup artifact size does not match its manifest on disk [{$diskName}].");
        }

        if ($this->hashRemoteFile($disk, $artifactPath) !== $manifest['sha256']) {
            throw new RuntimeException("Backup artifact checksum does not match its manifest on disk [{$diskName}].");
        }
    }

    private function hashRemoteFile(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Could not read database backup [{$path}] for verification.");
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function comparableManifest(array $manifest): array
    {
        unset($manifest['manifest_path']);

        return $manifest;
    }
}
