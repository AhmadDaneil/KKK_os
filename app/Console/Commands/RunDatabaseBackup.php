<?php

namespace App\Console\Commands;

use App\Services\Backup\ProductionDatabaseBackupService;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;

#[Signature('backup:database {--force : Run even when BACKUP_ENABLED is false}')]
#[Description('Create, replicate, verify, and retain a full KKK OS database backup')]
class RunDatabaseBackup extends Command implements Isolatable
{
    public function handle(ProductionDatabaseBackupService $backupService): int
    {
        if (! config('backup.enabled') && ! $this->option('force')) {
            $this->error('Database backup is disabled. Set BACKUP_ENABLED=true after both locations are configured.');

            return self::FAILURE;
        }

        try {
            $result = $backupService->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            if ($exception->getPrevious() !== null) {
                $this->line('Cause: '.$exception->getPrevious()->getMessage());
            }

            return self::FAILURE;
        }

        $this->info('Database backup created and verified in both configured locations.');
        $this->line('Backup ID: '.$result['backup_id']);
        $this->line('SHA-256: '.$result['sha256']);
        $this->line('Size: '.$result['size_bytes'].' bytes');
        $this->line('Disks: '.implode(', ', $result['disks']));

        if ($result['retention_errors'] !== []) {
            foreach ($result['retention_errors'] as $error) {
                $this->warn($error);
            }

            $this->error('The new backup is safe, but retention needs operator attention.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public function isolatableId(): string
    {
        return (string) (config('backup.database.connection') ?: config('database.default'));
    }

    public function isolationLockExpiresAt(): DateTimeInterface
    {
        return now()->addHours(6);
    }
}
