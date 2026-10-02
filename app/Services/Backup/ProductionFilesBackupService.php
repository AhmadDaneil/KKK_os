<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class ProductionFilesBackupService
{
    public function __construct(private FilesystemManager $filesystems) {}

    /**
     * @return array{
     *     backup_id: string,
     *     artifact_path: string,
     *     manifest_path: string,
     *     disks: list<string>,
     *     sha256: string,
     *     size_bytes: int,
     *     file_count: int,
     *     retention_errors: list<string>
     * }
     */
    public function run(?CarbonImmutable $startedAt = null): array
    {
        $startedAt ??= CarbonImmutable::now('UTC');
        $disks = $this->configuredDisks();
        $retentionDays = $this->retentionDays();
        $backupId = $startedAt->format('Ymd\THis\Z').'-'.Str::uuid()->toString();
        $archive = $this->createArchive($backupId);
        $directory = trim((string) config('backup.files.path', 'application-files'), '/').'/'.$startedAt->format('Y/m/d');
        $artifactPath = "{$directory}/kkk-os-files-{$backupId}.tar.gz";
        $manifestPath = "{$directory}/kkk-os-files-{$backupId}.manifest.json";
        $touchedDisks = [];

        try {
            $sha256 = $this->hashFile($archive['path']);
            $sizeBytes = $this->fileSize($archive['path']);
            $manifest = [
                'schema_version' => 'kkk-os-application-files-backup-v1',
                'backup_id' => $backupId,
                'created_at' => $startedAt->toIso8601String(),
                'archive_format' => 'tar-gzip',
                'artifact_path' => $artifactPath,
                'manifest_path' => $manifestPath,
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
                'file_count' => $archive['file_count'],
                'source_names' => array_keys($this->sources()),
                'retention_days' => $retentionDays,
            ];

            foreach ($disks as $diskName) {
                $touchedDisks[] = $diskName;
                $this->storeAndVerify($diskName, $archive['path'], $artifactPath, $manifestPath, $manifest);
            }
        } catch (Throwable $exception) {
            $this->rollBackCurrentBackup($touchedDisks, $artifactPath, $manifestPath);

            throw new RuntimeException(
                'Application files backup failed before both locations were verified. Retention was not run.',
                previous: $exception,
            );
        } finally {
            if (is_file($archive['path'])) {
                @unlink($archive['path']);
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
            'file_count' => $archive['file_count'],
            'retention_errors' => $retentionErrors,
        ];
    }

    /** @return array{path: string, file_count: int} */
    private function createArchive(string $backupId): array
    {
        $temporaryDirectory = (string) config('backup.files.temporary_directory');

        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Could not create the application files backup temporary directory.');
        }

        chmod($temporaryDirectory, 0700);
        $tarPath = rtrim($temporaryDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."kkk-os-files-{$backupId}.tar";
        $gzipPath = $tarPath.'.gz';
        @unlink($tarPath);
        @unlink($gzipPath);
        $archive = new PharData($tarPath);
        $fileCount = 0;

        try {
            foreach ($this->sources() as $name => $sourcePath) {
                if (! is_dir($sourcePath) || ! is_readable($sourcePath)) {
                    throw new RuntimeException("Application files source [{$name}] is missing or unreadable.");
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }

                    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourcePath) + 1));
                    $archive->addFile($file->getPathname(), $name.'/'.$relativePath);
                    $fileCount++;
                }
            }

            $archive->compress(Phar::GZ);
        } finally {
            unset($archive);
            @unlink($tarPath);
        }

        if (! is_file($gzipPath) || $fileCount < 1) {
            @unlink($gzipPath);
            throw new RuntimeException('Application files archive is empty or was not created.');
        }

        chmod($gzipPath, 0600);

        return ['path' => $gzipPath, 'file_count' => $fileCount];
    }

    /** @return array<string, string> */
    private function sources(): array
    {
        $sources = array_filter(
            (array) config('backup.files.sources', []),
            static fn (mixed $path, mixed $name): bool => is_string($name) && $name !== '' && is_string($path) && $path !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        if ($sources === []) {
            throw new RuntimeException('No application files backup sources are configured.');
        }

        return $sources;
    }

    /** @return list<string> */
    private function configuredDisks(): array
    {
        $disks = array_values(array_filter(
            (array) config('backup.files.disks', []),
            static fn (mixed $disk): bool => is_string($disk) && trim($disk) !== '',
        ));
        $disks = array_map(static fn (string $disk): string => trim($disk), $disks);

        if (count($disks) !== 2 || count(array_unique($disks)) !== 2) {
            throw new RuntimeException('Application files backup requires exactly two distinct filesystem disks.');
        }

        foreach ($disks as $disk) {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                throw new RuntimeException("Application files backup disk [{$disk}] is not configured.");
            }
        }

        return $disks;
    }

    private function retentionDays(): int
    {
        $days = (int) config('backup.files.retention_days', 30);

        if ($days < 30) {
            throw new RuntimeException('Application files backup retention must be at least 30 days.');
        }

        return $days;
    }

    /** @param array<string, mixed> $manifest */
    private function storeAndVerify(
        string $diskName,
        string $localPath,
        string $artifactPath,
        string $manifestPath,
        array $manifest,
    ): void {
        $disk = $this->filesystems->disk($diskName);
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Could not open the application files archive for replication.');
        }

        try {
            if (! $disk->writeStream($artifactPath, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException("Could not write the application files archive to disk [{$diskName}].");
            }
        } finally {
            fclose($stream);
        }

        if ($disk->size($artifactPath) !== (int) $manifest['size_bytes']
            || $this->hashRemoteFile($disk, $artifactPath) !== $manifest['sha256']) {
            throw new RuntimeException("Application files archive verification failed on disk [{$diskName}].");
        }

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (! $disk->put($manifestPath, $encoded, ['visibility' => 'private'])
            || json_decode($disk->get($manifestPath), true, flags: JSON_THROW_ON_ERROR) !== $manifest) {
            throw new RuntimeException("Application files manifest verification failed on disk [{$diskName}].");
        }
    }

    private function hashRemoteFile(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Could not read application files backup [{$path}] for verification.");
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /** @param list<string> $diskNames */
    private function rollBackCurrentBackup(array $diskNames, string $artifactPath, string $manifestPath): void
    {
        foreach (array_unique($diskNames) as $diskName) {
            try {
                $this->filesystems->disk($diskName)->delete([$artifactPath, $manifestPath]);
            } catch (Throwable) {
                // The original backup failure is reported; the next audit verifies cleanup.
            }
        }
    }

    /** @param list<string> $diskNames @return list<string> */
    private function pruneExpiredBackups(array $diskNames, CarbonImmutable $cutoff): array
    {
        $manifests = [];
        $errors = [];

        foreach ($diskNames as $diskName) {
            $manifests[$diskName] = [];
            $disk = $this->filesystems->disk($diskName);
            $root = trim((string) config('backup.files.path', 'application-files'), '/');

            foreach ($disk->allFiles($root) as $path) {
                if (! str_ends_with($path, '.manifest.json')) {
                    continue;
                }

                try {
                    $manifest = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);

                    if (! is_array($manifest)
                        || ($manifest['schema_version'] ?? null) !== 'kkk-os-application-files-backup-v1'
                        || ! is_string($manifest['backup_id'] ?? null)
                        || ! is_string($manifest['created_at'] ?? null)
                        || ! is_string($manifest['artifact_path'] ?? null)
                        || ($manifest['manifest_path'] ?? null) !== $path) {
                        throw new RuntimeException('Manifest does not match the application files backup contract.');
                    }

                    $manifests[$diskName][$manifest['backup_id']] = $manifest;
                } catch (Throwable $exception) {
                    $errors[] = "Retention ignored invalid manifest [{$path}] on disk [{$diskName}]: {$exception->getMessage()}";
                }
            }
        }

        $commonIds = array_intersect(array_keys($manifests[$diskNames[0]]), array_keys($manifests[$diskNames[1]]));

        foreach ($commonIds as $backupId) {
            $first = $manifests[$diskNames[0]][$backupId];
            $second = $manifests[$diskNames[1]][$backupId];

            if (($first['sha256'] ?? null) !== ($second['sha256'] ?? null)
                || ($first['artifact_path'] ?? null) !== ($second['artifact_path'] ?? null)) {
                $errors[] = "Skipped retention for files backup [{$backupId}] because the two manifests differ.";

                continue;
            }

            try {
                $createdAt = CarbonImmutable::parse($first['created_at'])->utc();
            } catch (Throwable) {
                $errors[] = "Skipped retention for files backup [{$backupId}] because created_at is invalid.";

                continue;
            }

            if ($createdAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            foreach ($diskNames as $diskName) {
                try {
                    $disk = $this->filesystems->disk($diskName);
                    $disk->delete([(string) $first['artifact_path'], (string) $first['manifest_path']]);
                } catch (Throwable $exception) {
                    $errors[] = "Retention could not remove files backup [{$backupId}] from disk [{$diskName}]: {$exception->getMessage()}";
                }
            }
        }

        return $errors;
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new RuntimeException('Could not calculate the application files archive checksum.');
        }

        return $hash;
    }

    private function fileSize(string $path): int
    {
        $size = filesize($path);

        if ($size === false || $size < 1) {
            throw new RuntimeException('The application files archive is empty.');
        }

        return $size;
    }
}
