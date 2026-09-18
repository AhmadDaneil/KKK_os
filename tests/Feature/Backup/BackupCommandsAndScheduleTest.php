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
}
