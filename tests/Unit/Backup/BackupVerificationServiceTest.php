<?php

namespace Tests\Unit\Backup;

use App\Services\Backup\BackupVerificationService;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupVerificationServiceTest extends TestCase
{
    public function test_latest_common_backup_is_verified_by_size_and_checksum_in_both_locations(): void
    {
        Storage::fake('backup-primary');
        Storage::fake('backup-secondary');
        $this->configureBackup();
        $this->storeBackup('backup-primary', 'older', '2026-09-16T02:00:00+00:00', 'old');
        $this->storeBackup('backup-secondary', 'older', '2026-09-16T02:00:00+00:00', 'old');
        $this->storeBackup('backup-primary', 'latest', '2026-09-17T02:00:00+00:00', 'new');
        $this->storeBackup('backup-secondary', 'latest', '2026-09-17T02:00:00+00:00', 'new');
        $service = new BackupVerificationService(app(FilesystemManager::class));

        $result = $service->verify();

        $this->assertSame('latest', $result['backup_id']);
        $this->assertSame(hash('sha256', 'new'), $result['sha256']);
        $this->assertSame(['backup-primary', 'backup-secondary'], $result['disks']);
    }

    public function test_checksum_mismatch_fails_verification(): void
    {
        Storage::fake('backup-primary');
        Storage::fake('backup-secondary');
        $this->configureBackup();
        $this->storeBackup('backup-primary', 'backup-one', '2026-09-17T02:00:00+00:00', 'AAAA');
        $this->storeBackup('backup-secondary', 'backup-one', '2026-09-17T02:00:00+00:00', 'AAAA');
        Storage::disk('backup-secondary')->put($this->artifactPath('backup-one'), 'BBBB');
        $service = new BackupVerificationService(app(FilesystemManager::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum does not match');

        $service->verify('backup-one');
    }

    private function configureBackup(): void
    {
        config([
            'backup.database.disks' => ['backup-primary', 'backup-secondary'],
            'backup.database.path' => 'database',
        ]);
    }

    private function storeBackup(string $diskName, string $backupId, string $createdAt, string $contents): void
    {
        $artifactPath = $this->artifactPath($backupId);
        $manifestPath = $this->manifestPath($backupId);
        Storage::disk($diskName)->put($artifactPath, $contents);
        Storage::disk($diskName)->put($manifestPath, json_encode([
            'schema_version' => 'kkk-os-database-backup-v1',
            'backup_id' => $backupId,
            'created_at' => $createdAt,
            'database_connection' => 'mysql',
            'database_driver' => 'mysql',
            'snapshot_format' => 'mysql-sql-gzip',
            'artifact_path' => $artifactPath,
            'manifest_path' => $manifestPath,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'retention_days' => 30,
        ], JSON_THROW_ON_ERROR));
    }

    private function artifactPath(string $backupId): string
    {
        return "database/2026/09/17/kkk-os-db-{$backupId}.sql.gz";
    }

    private function manifestPath(string $backupId): string
    {
        return "database/2026/09/17/kkk-os-db-{$backupId}.manifest.json";
    }
}
