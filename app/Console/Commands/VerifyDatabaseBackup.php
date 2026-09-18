<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupVerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:verify {backupId? : Backup ID to verify; defaults to the latest common backup}')]
#[Description('Verify a database backup manifest and SHA-256 checksum in both locations')]
class VerifyDatabaseBackup extends Command
{
    public function handle(BackupVerificationService $verificationService): int
    {
        try {
            $result = $verificationService->verify($this->argument('backupId'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database backup integrity verified in both configured locations.');
        $this->line('Backup ID: '.$result['backup_id']);
        $this->line('Created: '.$result['created_at']);
        $this->line('SHA-256: '.$result['sha256']);
        $this->line('Size: '.$result['size_bytes'].' bytes');
        $this->line('Disks: '.implode(', ', $result['disks']));

        return self::SUCCESS;
    }
}
