<?php

namespace App\Services\Backup;

use App\Contracts\Backup\DatabaseSnapshotter;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProductionDatabaseBackupService
{
    public function __construct(
        private DatabaseSnapshotter $snapshotter,
        private FilesystemManager $filesystems,
    ) {}

    /**
     * @return array{
     *     backup_id: string,
     *     artifact_path: string,
     *     manifest_path: string,
     *     disks: list<string>,
     *     sha256: string,
     *     size_bytes: int,
     *     retention_errors: list<string>
     * }
     */
    public function run(?CarbonImmutable $startedAt = null): array
    {
        $startedAt ??= CarbonImmutable::now('UTC');
        $connectionName = (string) (config('backup.database.connection') ?: config('database.default'));
        $disks = $this->configuredDisks();
        $retentionDays = $this->retentionDays();
        $temporaryDirectory = (string) config('backup.database.temporary_directory');
        $snapshot = $this->snapshotter->create($connectionName, $temporaryDirectory);
        $backupId = $startedAt->format('Ymd\THis\Z').'-'.Str::uuid()->toString();
        $directory = trim((string) config('backup.database.path', 'database'), '/').'/'.$startedAt->format('Y/m/d');
        $artifactPath = "{$directory}/kkk-os-db-{$backupId}.{$snapshot->extension}";
        $manifestPath = "{$directory}/kkk-os-db-{$backupId}.manifest.json";
        $touchedDisks = [];

        try {
            $sha256 = $this->hashFile($snapshot->path);
            $sizeBytes = $this->fileSize($snapshot->path);
            $manifest = $this->buildManifest(
                backupId: $backupId,
                startedAt: $startedAt,
                connectionName: $connectionName,
                snapshot: $snapshot,
                artifactPath: $artifactPath,
                manifestPath: $manifestPath,
                sha256: $sha256,
                sizeBytes: $sizeBytes,
                retentionDays: $retentionDays,
            );

            foreach ($disks as $diskName) {
                $touchedDisks[] = $diskName;
                $this->storeAndVerify(
                    diskName: $diskName,
                    snapshotPath: $snapshot->path,
                    artifactPath: $artifactPath,
                    manifestPath: $manifestPath,
                    manifest: $manifest,
                );
            }
        } catch (Throwable $exception) {
            $this->rollBackCurrentBackup($touchedDisks, $artifactPath, $manifestPath);

            throw new RuntimeException(
                'Database backup failed before both locations were verified. Retention was not run.',
                previous: $exception,
            );
        } finally {
            if (is_file($snapshot->path)) {
                @unlink($snapshot->path);
            }
        }

        $retentionErrors = $this->pruneExpiredBackups($disks, $startedAt->subDays($retentionDays));

        return [
            'backup_id' => $backupId,
            'artifact_path' => $artifactPath,
            'manifest_path' => $manifestPath,
            'disks' => $disks,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
            'retention_errors' => $retentionErrors,
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
            throw new RuntimeException('Database backup requires exactly two distinct filesystem disks.');
        }

        foreach ($disks as $disk) {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                throw new RuntimeException("Database backup disk [{$disk}] is not configured.");
            }
        }

        return $disks;
    }

    private function retentionDays(): int
    {
        $retentionDays = (int) config('backup.database.retention_days', 30);

        if ($retentionDays < 1) {
            throw new RuntimeException('Database backup retention must be at least one day.');
        }

        return $retentionDays;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManifest(
        string $backupId,
        CarbonImmutable $startedAt,
        string $connectionName,
        DatabaseSnapshot $snapshot,
        string $artifactPath,
        string $manifestPath,
        string $sha256,
        int $sizeBytes,
        int $retentionDays,
    ): array {
        return [
            'schema_version' => 'kkk-os-database-backup-v1',
            'backup_id' => $backupId,
            'created_at' => $startedAt->toIso8601String(),
            'database_connection' => $connectionName,
            'database_driver' => (string) config("database.connections.{$connectionName}.driver"),
            'snapshot_format' => $snapshot->format,
            'artifact_path' => $artifactPath,
            'manifest_path' => $manifestPath,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
            'retention_days' => $retentionDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function storeAndVerify(
        string $diskName,
        string $snapshotPath,
        string $artifactPath,
        string $manifestPath,
        array $manifest,
    ): void {
        $disk = $this->filesystems->disk($diskName);
        $stream = fopen($snapshotPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Could not open the local database snapshot for replication.');
        }

        try {
            if (! $disk->writeStream($artifactPath, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException("Could not write the database snapshot to disk [{$diskName}].");
            }
        } finally {
            fclose($stream);
        }

        if ($disk->size($artifactPath) !== (int) $manifest['size_bytes']) {
            throw new RuntimeException("Database snapshot size verification failed on disk [{$diskName}].");
        }

        if ($this->hashRemoteFile($disk, $artifactPath) !== $manifest['sha256']) {
            throw new RuntimeException("Database snapshot checksum verification failed on disk [{$diskName}].");
        }

        $encodedManifest = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;

        if (! $disk->put($manifestPath, $encodedManifest, ['visibility' => 'private'])) {
            throw new RuntimeException("Could not write the database backup manifest to disk [{$diskName}].");
        }

        $storedManifest = json_decode($disk->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        if ($storedManifest !== $manifest) {
            throw new RuntimeException("Database backup manifest verification failed on disk [{$diskName}].");
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
     * @param  list<string>  $diskNames
     */
    private function rollBackCurrentBackup(array $diskNames, string $artifactPath, string $manifestPath): void
    {
        foreach (array_unique($diskNames) as $diskName) {
            try {
                $this->filesystems->disk($diskName)->delete([$artifactPath, $manifestPath]);
            } catch (Throwable) {
                // A failed rollback is reported by the original failed backup run and verified on the next audit.
            }
        }
    }

    /**
     * @param  list<string>  $diskNames
     * @return list<string>
     */
    private function pruneExpiredBackups(array $diskNames, CarbonImmutable $cutoff): array
    {
        [$firstManifests, $errors] = $this->readRetentionManifests($diskNames[0]);
        [$secondManifests, $secondErrors] = $this->readRetentionManifests($diskNames[1]);
        $errors = [...$errors, ...$secondErrors];
        $commonBackupIds = array_intersect(array_keys($firstManifests), array_keys($secondManifests));

        foreach ($commonBackupIds as $backupId) {
            $first = $firstManifests[$backupId];
            $second = $secondManifests[$backupId];

            if ($first['sha256'] !== $second['sha256'] || $first['artifact_path'] !== $second['artifact_path']) {
                $errors[] = "Skipped retention for [{$backupId}] because the two manifests differ.";

                continue;
            }

            try {
                $createdAt = CarbonImmutable::parse($first['created_at'])->utc();
            } catch (Throwable) {
                $errors[] = "Skipped retention for [{$backupId}] because created_at is invalid.";

                continue;
            }

            if ($createdAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            foreach ($diskNames as $diskName) {
                try {
                    $disk = $this->filesystems->disk($diskName);
                    $artifactPath = (string) $first['artifact_path'];
                    $manifestPath = (string) $first['manifest_path'];

                    if (! $disk->delete($artifactPath) && $disk->exists($artifactPath)) {
                        throw new RuntimeException('The expired backup artifact could not be deleted.');
                    }

                    if (! $disk->delete($manifestPath) && $disk->exists($manifestPath)) {
                        throw new RuntimeException('The expired backup manifest could not be deleted.');
                    }
                } catch (Throwable $exception) {
                    $errors[] = "Retention could not remove [{$backupId}] from disk [{$diskName}]: {$exception->getMessage()}";
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    private function readRetentionManifests(string $diskName): array
    {
        $disk = $this->filesystems->disk($diskName);
        $manifests = [];
        $errors = [];
        $root = trim((string) config('backup.database.path', 'database'), '/');

        foreach ($disk->allFiles($root) as $path) {
            if (! str_ends_with($path, '.manifest.json')) {
                continue;
            }

            try {
                $manifest = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($manifest)
                    || ($manifest['schema_version'] ?? null) !== 'kkk-os-database-backup-v1'
                    || ! is_string($manifest['backup_id'] ?? null)
                    || ! is_string($manifest['created_at'] ?? null)
                    || ! is_string($manifest['artifact_path'] ?? null)
                    || ! is_string($manifest['sha256'] ?? null)
                    || ($manifest['manifest_path'] ?? null) !== $path
                    || ! str_starts_with($manifest['artifact_path'], $root.'/')) {
                    throw new RuntimeException('Manifest does not match the V1 backup contract.');
                }

                $manifests[$manifest['backup_id']] = $manifest;
            } catch (Throwable $exception) {
                $errors[] = "Retention ignored invalid manifest [{$path}] on disk [{$diskName}]: {$exception->getMessage()}";
            }
        }

        return [$manifests, $errors];
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new RuntimeException('Could not calculate the local database snapshot checksum.');
        }

        return $hash;
    }

    private function fileSize(string $path): int
    {
        $size = filesize($path);

        if ($size === false || $size < 1) {
            throw new RuntimeException('The local database snapshot is empty.');
        }

        return $size;
    }
}
