<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupsCleanupCommand extends Command
{
    protected $signature = 'backups:cleanup';

    protected $description = 'Delete completed backups older than the configured retention period';

    public function handle(BackupService $backupService): int
    {
        $keepDays = (int) config('backups.keep_days', 30);
        $this->info("Removing completed backups older than {$keepDays} days...");

        $deleted = $backupService->cleanupExpiredBackups();

        $this->info("Deleted {$deleted} backup(s).");

        return self::SUCCESS;
    }
}
