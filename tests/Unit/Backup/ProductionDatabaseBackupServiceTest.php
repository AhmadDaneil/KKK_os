<?php

namespace Tests\Unit\Backup;

use App\Contracts\Backup\DatabaseSnapshotter;
use App\Services\Backup\DatabaseSnapshot;
use App\Services\Backup\ProductionDatabaseBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductionDatabaseBackupServiceTest extends TestCase
{
    public function test_successful_backup_is_verified_in_two_locations_before_expired_backups_are_removed(): void
    {
        Storage::fake('backup-primary');
        Storage::fake('backup-secondary');
        $this->configureBackup(['backup-primary', 'backup-secondary']);
        $this->storeBackupFixture('backup-primary', 'expired-backup', '2026-08-18T01:00:00+00:00', 'expired');
        $this->storeBackupFixture('backup-secondary', 'expired-backup', '2026-08-18T01:00:00+00:00', 'expired');
        $this->storeBackupFixture('backup-primary', 'retained-backup', '2026-08-20T01:00:00+00:00', 'retained');
        $this->storeBackupFixture('backup-secondary', 'retained-backup', '2026-08-20T01:00:00+00:00', 'retained');
        $snapshotter = $this->snapshotter('current full database snapshot');
        $service = new ProductionDatabaseBackupService($snapshotter, app(FilesystemManager::class));

        $result = $service->run(CarbonImmutable::parse('2026-09-18T02:00:00+00:00'));

        $this->assertSame(['backup-primary', 'backup-secondary'], $result['disks']);
        $this->assertSame([], $result['retention_errors']);
        Storage::disk('backup-primary')->assertExists($result['artifact_path']);
        Storage::disk('backup-secondary')->assertExists($result['artifact_path']);
        Storage::disk('backup-primary')->assertExists($result['manifest_path']);
        Storage::disk('backup-secondary')->assertExists($result['manifest_path']);
        Storage::disk('backup-primary')->assertMissing($this->artifactPath('expired-backup'));
        Storage::disk('backup-secondary')->assertMissing($this->artifactPath('expired-backup'));
        Storage::disk('backup-primary')->assertMissing($this->manifestPath('expired-backup'));
        Storage::disk('backup-secondary')->assertMissing($this->manifestPath('expired-backup'));
        Storage::disk('backup-primary')->assertExists($this->artifactPath('retained-backup'));
        Storage::disk('backup-secondary')->assertExists($this->artifactPath('retained-backup'));
        $this->assertSame(
            Storage::disk('backup-primary')->get($result['manifest_path']),
            Storage::disk('backup-secondary')->get($result['manifest_path']),
        );
        $this->assertFileDoesNotExist($snapshotter->lastSnapshotPath);
    }

    public function test_replication_failure_rolls_back_the_new_copy_and_does_not_run_retention(): void
    {
        Storage::fake('backup-primary');
        $blockingPath = storage_path('framework/testing/backup-disk-blocker');
        @mkdir(dirname($blockingPath), 0775, true);
        file_put_contents($blockingPath, 'not a directory');
        config([
            'filesystems.disks.backup-failing' => [
                'driver' => 'local',
                'root' => $blockingPath,
                'throw' => true,
            ],
        ]);
        $this->configureBackup(['backup-primary', 'backup-failing']);
        $this->storeBackupFixture('backup-primary', 'expired-backup', '2026-08-18T01:00:00+00:00', 'expired');
        $snapshotter = $this->snapshotter('current full database snapshot');
        $service = new ProductionDatabaseBackupService($snapshotter, app(FilesystemManager::class));

        try {
            $service->run(CarbonImmutable::parse('2026-09-18T02:00:00+00:00'));
            $this->fail('The backup should fail when the second location cannot be written.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Database backup failed before both locations were verified. Retention was not run.',
                $exception->getMessage(),
            );
        } finally {
            @unlink($blockingPath);
        }

        Storage::disk('backup-primary')->assertExists($this->artifactPath('expired-backup'));
        Storage::disk('backup-primary')->assertExists($this->manifestPath('expired-backup'));
        $this->assertCount(2, Storage::disk('backup-primary')->allFiles('database'));
        $this->assertFileDoesNotExist($snapshotter->lastSnapshotPath);
    }

    public function test_backup_is_rejected_until_two_distinct_locations_are_configured(): void
    {
        Storage::fake('backup-primary');
        $this->configureBackup(['backup-primary', 'backup-primary']);
        $snapshotter = $this->snapshotter('snapshot must not be created');
        $service = new ProductionDatabaseBackupService($snapshotter, app(FilesystemManager::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database backup requires exactly two distinct filesystem disks.');

        $service->run(CarbonImmutable::parse('2026-09-18T02:00:00+00:00'));
    }

    /**
     * @param  list<string>  $disks
     */
    private function configureBackup(array $disks): void
    {
        $configuration = [
            'backup.database.connection' => 'sqlite',
            'backup.database.disks' => $disks,
            'backup.database.path' => 'database',
            'backup.database.retention_days' => 30,
            'backup.database.temporary_directory' => storage_path('framework/testing/backup-temp'),
            'database.connections.sqlite.driver' => 'sqlite',
        ];

        foreach (array_unique($disks) as $disk) {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                $configuration["filesystems.disks.{$disk}"] = [
                    'driver' => 'local',
                    'root' => storage_path("framework/testing/disks/{$disk}"),
                ];
            }
        }

        config($configuration);
    }

    private function snapshotter(string $contents): DatabaseSnapshotter
    {
        return new class($contents) implements DatabaseSnapshotter
        {
            public ?string $lastSnapshotPath = null;

            public function __construct(private string $contents) {}

            public function create(string $connectionName, string $workingDirectory): DatabaseSnapshot
            {
                @mkdir($workingDirectory, 0700, true);
                $this->lastSnapshotPath = $workingDirectory.DIRECTORY_SEPARATOR.'database.sql.gz';
                file_put_contents($this->lastSnapshotPath, $this->contents);

                return new DatabaseSnapshot($this->lastSnapshotPath, 'mysql-sql-gzip', 'sql.gz');
            }
        };
    }

    private function storeBackupFixture(
        string $diskName,
        string $backupId,
        string $createdAt,
        string $contents,
    ): void {
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
        return "database/2026/08/18/kkk-os-db-{$backupId}.sql.gz";
    }

    private function manifestPath(string $backupId): string
    {
        return "database/2026/08/18/kkk-os-db-{$backupId}.manifest.json";
    }
}
