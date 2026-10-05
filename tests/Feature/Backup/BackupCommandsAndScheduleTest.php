<?php

namespace Tests\Feature\Backup;

use App\Contracts\Backup\DatabaseSnapshotter;
use App\Services\Backup\DatabaseSnapshot;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCommandsAndScheduleTest extends TestCase
{
    public function test_database_backup_and_verification_commands_complete_for_two_locations(): void
    {
        Storage::fake('backup-primary');
        Storage::fake('backup-secondary');
        config([
            'backup.enabled' => true,
            'backup.database.connection' => 'sqlite',
            'backup.database.disks' => ['backup-primary', 'backup-secondary'],
            'backup.database.path' => 'database',
            'backup.database.retention_days' => 30,
            'backup.database.temporary_directory' => storage_path('framework/testing/backup-command-temp'),
            'database.connections.sqlite.driver' => 'sqlite',
            'filesystems.disks.backup-primary' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-primary'),
            ],
            'filesystems.disks.backup-secondary' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-secondary'),
            ],
        ]);
        $this->app->bind(DatabaseSnapshotter::class, static fn (): DatabaseSnapshotter => new class implements DatabaseSnapshotter
        {
            public function create(string $connectionName, string $workingDirectory): DatabaseSnapshot
            {
                @mkdir($workingDirectory, 0700, true);
                $path = $workingDirectory.DIRECTORY_SEPARATOR.'command.sql.gz';
                file_put_contents($path, 'command snapshot');

                return new DatabaseSnapshot($path, 'mysql-sql-gzip', 'sql.gz');
            }
        });

        $this->artisan('backup:database')
            ->expectsOutputToContain('created and verified')
            ->assertExitCode(0);

        $this->artisan('backup:verify')
            ->expectsOutputToContain('integrity verified')
            ->assertExitCode(0);
    }

    public function test_daily_production_schedule_registers_the_isolated_backup_command(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(static fn ($event): bool => str_contains((string) $event->command, 'backup:database --isolated'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertSame(['production'], $event->environments);
    }

    public function test_daily_production_schedule_registers_the_files_backup_after_database(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(static fn ($event): bool => str_contains((string) $event->command, 'backup:files --isolated'));

        $this->assertNotNull($event);
        $this->assertSame('30 2 * * *', $event->expression);
        $this->assertSame(['production'], $event->environments);
    }

    public function test_application_files_backup_archives_private_and_public_storage_to_two_locations(): void
    {
        Storage::fake('backup-primary');
        Storage::fake('backup-secondary');
        $sourceRoot = storage_path('framework/testing/files-backup-sources');
        @mkdir($sourceRoot.'/private', 0700, true);
        @mkdir($sourceRoot.'/public', 0700, true);
        file_put_contents($sourceRoot.'/private/customer.txt', 'private customer file');
        file_put_contents($sourceRoot.'/public/output.txt', 'public generated file');

        config([
            'backup.enabled' => true,
            'backup.files.disks' => ['backup-primary', 'backup-secondary'],
            'backup.files.path' => 'application-files',
            'backup.files.sources' => [
                'private' => $sourceRoot.'/private',
                'public' => $sourceRoot.'/public',
            ],
            'backup.files.retention_days' => 30,
            'backup.files.temporary_directory' => storage_path('framework/testing/files-backup-temp'),
            'filesystems.disks.backup-primary' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/files-backup-primary'),
            ],
            'filesystems.disks.backup-secondary' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/files-backup-secondary'),
            ],
        ]);

        $this->artisan('backup:files')
            ->expectsOutputToContain('created and verified')
            ->expectsOutputToContain('Files: 2')
            ->assertExitCode(0);

        $primaryFiles = Storage::disk('backup-primary')->allFiles('application-files');
        $secondaryFiles = Storage::disk('backup-secondary')->allFiles('application-files');
        $this->assertCount(2, $primaryFiles);
        $this->assertSame($primaryFiles, $secondaryFiles);
        $this->assertTrue(collect($primaryFiles)->contains(
            static fn (string $path): bool => str_ends_with($path, '.manifest.json'),
        ));
        $this->assertTrue(collect($primaryFiles)->contains(
            static fn (string $path): bool => str_ends_with($path, '.tar.gz'),
        ));
    }

    public function test_production_backup_disks_use_private_local_and_s3_storage(): void
    {
        $primary = config('filesystems.disks.backup_primary');
        $secondary = config('filesystems.disks.backup_secondary');

        $this->assertSame('local', $primary['driver']);
        $this->assertFalse($primary['serve']);
        $this->assertTrue($primary['throw']);
        $this->assertSame(0600, $primary['permissions']['file']['private']);
        $this->assertSame(0700, $primary['permissions']['dir']['private']);
        $this->assertSame('s3', $secondary['driver']);
        $this->assertSame('private', $secondary['visibility']);
        $this->assertTrue($secondary['throw']);
    }
}
