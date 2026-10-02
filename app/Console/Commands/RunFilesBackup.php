<?php

namespace App\Console\Commands;

use App\Services\Backup\ProductionFilesBackupService;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('backup:files {--force : Run even when BACKUP_ENABLED is false}')]
#[Description('Archive, replicate, verify, and retain KKK OS application files')]
class RunFilesBackup extends Command implements Isolatable
{
    public function handle(ProductionFilesBackupService $backupService): int
    {
        if (! config('backup.enabled') && ! $this->option('force')) {
            $this->error('Application files backup is disabled. Set BACKUP_ENABLED=true after restore testing.');

            return self::FAILURE;
        }

        try {
            $result = $backupService->run();
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Application files backup failed.', ['exception' => $exception]);
            $this->error($exception->getMessage());

            if ($exception->getPrevious() !== null) {
                $this->line('Cause: '.$exception->getPrevious()->getMessage());
            }

            return self::FAILURE;
        }

        Log::info('Application files backup completed and verified.', [
            'backup_id' => $result['backup_id'],
            'sha256' => $result['sha256'],
            'size_bytes' => $result['size_bytes'],
            'disks' => $result['disks'],
            'retention_errors' => $result['retention_errors'],
        ]);

        $this->info('Application files backup created and verified in both configured locations.');
        $this->line('Backup ID: '.$result['backup_id']);
        $this->line('SHA-256: '.$result['sha256']);
        $this->line('Size: '.$result['size_bytes'].' bytes');
        $this->line('Files: '.$result['file_count']);
        $this->line('Disks: '.implode(', ', $result['disks']));

        if ($result['retention_errors'] !== []) {
            foreach ($result['retention_errors'] as $error) {
                $this->warn($error);
            }

            $this->error('The new files backup is safe, but retention needs operator attention.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public function isolatableId(): string
    {
        return 'application-files';
    }

    public function isolationLockExpiresAt(): DateTimeInterface
    {
        return now()->addHours(6);
    }
}
