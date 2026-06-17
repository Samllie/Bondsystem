<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupsCreateCommand extends Command
{
    protected $signature = 'backups:create {type : database, files, or full} {--notes= : Optional backup notes}';

    protected $description = 'Create a local backup archive (database, files, or full)';

    public function handle(BackupService $backupService): int
    {
        $typeValue = strtolower((string) $this->argument('type'));

        if (! in_array($typeValue, ['database', 'files', 'full'], true)) {
            $this->error('Type must be one of: database, files, full');

            return self::FAILURE;
        }

        $type = BackupType::from($typeValue);
        $notes = $this->option('notes');

        $this->info("Creating {$type->label()} backup...");

        $record = match ($type) {
            BackupType::Database => $backupService->createDatabaseBackup(null, $notes),
            BackupType::Files => $backupService->createFilesBackup(null, $notes),
            BackupType::Full => $backupService->createFullBackup(null, $notes),
        };

        if ($record->backup_status === BackupStatus::Failed) {
            $this->error($record->verification_message ?? 'Backup failed.');

            return self::FAILURE;
        }

        $this->info("Backup created: {$record->filename} ({$record->file_size} bytes)");

        return self::SUCCESS;
    }
}
