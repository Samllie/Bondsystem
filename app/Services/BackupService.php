<?php

namespace App\Services;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\BackupRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupService
{
    public function createDatabaseBackup(?User $user = null, ?string $notes = null): BackupRecord
    {
        return $this->runBackup(BackupType::Database, $user, $notes, function (BackupRecord $record, string $absolutePath): void {
            $this->dumpDatabase($absolutePath);
        });
    }

    public function createFilesBackup(?User $user = null, ?string $notes = null): BackupRecord
    {
        return $this->runBackup(BackupType::Files, $user, $notes, function (BackupRecord $record, string $absolutePath): void {
            $this->createFilesArchive($absolutePath);
        });
    }

    public function createFullBackup(?User $user = null, ?string $notes = null): BackupRecord
    {
        return $this->runBackup(BackupType::Full, $user, $notes, function (BackupRecord $record, string $absolutePath): void {
            $this->createFullArchive($absolutePath);
        });
    }

    public function deleteBackup(BackupRecord $record, ?User $user = null): bool
    {
        try {
            $absolutePath = $record->absolutePath();

            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            AuditLogService::log(
                user: $user,
                action: 'backup_deleted',
                entityType: AuditLogService::ENTITY_BACKUP,
                entityId: $record->id,
                oldValues: [
                    'backup_type' => $record->backup_type?->value,
                    'filename' => $record->filename,
                ],
                description: "Backup {$record->filename} deleted.",
            );

            $record->delete();

            return true;
        } catch (Throwable $exception) {
            Log::error('Backup deletion failed: '.$exception->getMessage(), [
                'backup_id' => $record->id,
            ]);

            return false;
        }
    }

    public function verifyBackup(BackupRecord $record, ?User $user = null): bool
    {
        $message = $this->performVerification($record);
        $passed = str_starts_with($message, 'OK:');

        $updates = [
            'verification_passed' => $passed,
            'verified_at' => now(),
            'verification_message' => $message,
        ];

        if (! $passed) {
            $updates['backup_status'] = BackupStatus::Failed;
        }

        $record->update($updates);

        AuditLogService::log(
            user: $user,
            action: 'backup_verified',
            entityType: AuditLogService::ENTITY_BACKUP,
            entityId: $record->id,
            newValues: [
                'backup_type' => $record->backup_type?->value,
                'filename' => $record->filename,
                'verification_passed' => $passed,
            ],
            description: $passed
                ? "Backup {$record->filename} verified successfully."
                : "Backup {$record->filename} failed verification.",
        );

        return $passed;
    }

    public function cleanupExpiredBackups(): int
    {
        $keepDays = (int) config('backups.keep_days', 30);
        $cutoff = now()->subDays($keepDays);
        $deleted = 0;

        BackupRecord::query()
            ->where('backup_status', BackupStatus::Completed)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (BackupRecord $record) use (&$deleted): void {
                if ($this->deleteBackup($record)) {
                    $deleted++;
                }
            });

        return $deleted;
    }

    /**
     * @param  callable(BackupRecord, string): void  $writer
     */
    private function runBackup(
        BackupType $type,
        ?User $user,
        ?string $notes,
        callable $writer,
    ): BackupRecord {
        [$filename, $relativePath, $absolutePath] = $this->buildPaths($type);

        $record = BackupRecord::query()->create([
            'backup_type' => $type,
            'filename' => $filename,
            'file_path' => $relativePath,
            'file_size' => 0,
            'backup_status' => BackupStatus::Running,
            'started_at' => now(),
            'created_by' => $user?->id,
            'notes' => $notes,
        ]);

        try {
            $this->ensureDirectory(dirname($absolutePath));
            $writer($record, $absolutePath);

            if (! file_exists($absolutePath) || filesize($absolutePath) <= 0) {
                throw new \RuntimeException('Backup file was not created or is empty.');
            }

            $record->update([
                'file_size' => (int) filesize($absolutePath),
                'backup_status' => BackupStatus::Completed,
                'completed_at' => now(),
            ]);

            if (! $this->verifyBackup($record, $user)) {
                return $record->fresh(['creator']);
            }

            AuditLogService::log(
                user: $user,
                action: 'backup_created',
                entityType: AuditLogService::ENTITY_BACKUP,
                entityId: $record->id,
                newValues: [
                    'backup_type' => $type->value,
                    'filename' => $filename,
                    'file_size' => $record->file_size,
                ],
                description: "{$type->label()} backup {$filename} created.",
            );

            return $record->fresh(['creator']);
        } catch (Throwable $exception) {
            Log::error('Backup creation failed: '.$exception->getMessage(), [
                'backup_id' => $record->id,
                'backup_type' => $type->value,
            ]);

            if (isset($absolutePath) && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }

            $record->update([
                'backup_status' => BackupStatus::Failed,
                'completed_at' => now(),
                'verification_passed' => false,
                'verified_at' => now(),
                'verification_message' => $exception->getMessage(),
            ]);

            return $record->fresh(['creator']);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildPaths(BackupType $type): array
    {
        $timestamp = now()->format('Y_m_d_His');

        [$subdir, $filename] = match ($type) {
            BackupType::Database => ['database', "backup_{$timestamp}.sql"],
            BackupType::Files => ['files', "files_{$timestamp}.zip"],
            BackupType::Full => ['full', "full_backup_{$timestamp}.zip"],
        };

        $relativePath = trim(config('backups.storage_root', 'backups'), '/')."/{$subdir}/{$filename}";
        $absolutePath = storage_path('app/private/'.$relativePath);

        return [$filename, $relativePath, $absolutePath];
    }

    private function dumpDatabase(string $absolutePath): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver === 'mysql' && $this->mysqldumpIsAvailable()) {
            $this->dumpDatabaseWithMysqldump($absolutePath, $connection);

            return;
        }

        $this->dumpDatabaseWithPhp($absolutePath, $connection);
    }

    private function mysqldumpIsAvailable(): bool
    {
        $binary = (string) config('backups.mysqldump_path', 'mysqldump');
        $process = new Process([$binary, '--version']);
        $process->setTimeout(10);

        try {
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function dumpDatabaseWithMysqldump(string $absolutePath, string $connection): void
    {
        $config = config("database.connections.{$connection}");
        $binary = (string) config('backups.mysqldump_path', 'mysqldump');

        $command = [
            $binary,
            '--no-tablespaces',
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? '3306'),
            '-u', (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
        ];

        $process = new Process($command);
        $process->setTimeout(null);

        if (! empty($config['password'])) {
            $process->setEnv([
                'MYSQL_PWD' => (string) $config['password'],
            ]);
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open database backup file for writing.');
        }

        $process->run(function (string $type, string $buffer) use ($handle): void {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });

        fclose($handle);

        if (! $process->isSuccessful()) {
            @unlink($absolutePath);

            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump failed.');
        }
    }

    private function dumpDatabaseWithPhp(string $absolutePath, string $connection): void
    {
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open database backup file for writing.');
        }

        $driver = (string) config("database.connections.{$connection}.driver");

        try {
            fwrite($handle, "-- Bond System database backup\n");
            fwrite($handle, '-- Generated at '.now()->toIso8601String()."\n\n");

            $tables = $driver === 'sqlite'
                ? collect(DB::connection($connection)->select(
                    "SELECT name AS table_name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                ))
                : collect(DB::connection($connection)->select('SHOW TABLES'))
                    ->map(fn ($row) => (object) ['table_name' => array_values((array) $row)[0]]);

            foreach ($tables as $tableRow) {
                $table = (string) $tableRow->table_name;
                $this->dumpTableWithPhp($handle, $connection, $table);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function dumpTableWithPhp($handle, string $connection, string $table): void
    {
        $driver = (string) config("database.connections.{$connection}.driver");

        fwrite($handle, "\n-- Table: {$table}\n");

        if ($driver === 'sqlite') {
            $create = DB::connection($connection)->selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
            fwrite($handle, ($create->sql ?? '').";\n\n");
        } else {
            $create = DB::connection($connection)->selectOne("SHOW CREATE TABLE `{$table}`");
            $createStatement = $create->{'Create Table'} ?? null;
            fwrite($handle, ($createStatement ?? '').";\n\n");
        }

        DB::connection($connection)->table($table)->orderBy(DB::connection($connection)->raw('1'))->chunk(200, function ($rows) use ($handle, $table, $connection, $driver): void {
            foreach ($rows as $row) {
                $columns = array_keys((array) $row);
                $values = array_map(function ($value) use ($connection) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    if (is_bool($value)) {
                        return $value ? '1' : '0';
                    }

                    if (is_numeric($value)) {
                        return (string) $value;
                    }

                    return DB::connection($connection)->getPdo()->quote((string) $value);
                }, array_values((array) $row));

                $columnList = implode(', ', array_map(fn ($column) => $driver === 'mysql' ? "`{$column}`" : $column, $columns));
                $valueList = implode(', ', $values);
                fwrite($handle, "INSERT INTO {$table} ({$columnList}) VALUES ({$valueList});\n");
            }
        });
    }

    private function createFilesArchive(string $absolutePath): void
    {
        $zip = new ZipArchive;
        $result = $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException('Unable to create files backup archive.');
        }

        try {
            foreach ($this->protectedPaths() as $path) {
                $this->addPathToZip($zip, $path);
            }
        } finally {
            $zip->close();
        }
    }

    private function createFullArchive(string $absolutePath): void
    {
        $zip = new ZipArchive;
        $result = $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException('Unable to create full backup archive.');
        }

        $sqlTemp = tempnam(sys_get_temp_dir(), 'bond_db_');

        if ($sqlTemp === false) {
            $zip->close();

            throw new \RuntimeException('Unable to allocate temporary database backup file.');
        }

        try {
            $this->dumpDatabase($sqlTemp);
            $zip->addFile($sqlTemp, 'database/backup.sql');

            foreach ($this->protectedPaths() as $path) {
                $this->addPathToZip($zip, $path, 'files');
            }
        } finally {
            $zip->close();

            if (file_exists($sqlTemp)) {
                @unlink($sqlTemp);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function protectedPaths(): array
    {
        $backupsRoot = storage_path('app/private/'.trim(config('backups.storage_root', 'backups'), '/'));

        return collect(config('backups.file_paths', []))
            ->filter(fn ($path) => is_string($path) && $path !== '' && file_exists($path))
            ->reject(fn (string $path) => str_starts_with(realpath($path) ?: $path, realpath($backupsRoot) ?: $backupsRoot))
            ->values()
            ->all();
    }

    private function addPathToZip(ZipArchive $zip, string $path, string $prefix = ''): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            $localName = ltrim($prefix.'/'.$this->relativeArchiveName($path), '/');
            $zip->addFile($path, $localName);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $filePath = $fileInfo->getPathname();

            if ($fileInfo->isDir()) {
                continue;
            }

            $localName = ltrim($prefix.'/'.$this->relativeArchiveName($filePath), '/');
            $zip->addFile($filePath, $localName);
        }
    }

    private function relativeArchiveName(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        foreach ([
            str_replace('\\', '/', storage_path('app/private/')),
            str_replace('\\', '/', storage_path('app/public/')),
            str_replace('\\', '/', resource_path('templates/')),
        ] as $base) {
            if (str_starts_with($normalized, $base)) {
                return ltrim(substr($normalized, strlen($base)), '/');
            }
        }

        return basename($absolutePath);
    }

    private function performVerification(BackupRecord $record): string
    {
        $absolutePath = $record->absolutePath();

        if (! file_exists($absolutePath)) {
            return 'ERROR: Backup file does not exist.';
        }

        $size = filesize($absolutePath);

        if ($size === false || $size <= 0) {
            return 'ERROR: Backup file is empty.';
        }

        return match ($record->backup_type) {
            BackupType::Database => $this->verifySqlFile($absolutePath),
            BackupType::Files, BackupType::Full => $this->verifyZipFile($absolutePath, $record->backup_type),
            default => 'ERROR: Unknown backup type.',
        };
    }

    private function verifySqlFile(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            return 'ERROR: Unable to read SQL backup file.';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false || trim($firstLine) === '') {
            return 'ERROR: SQL backup file appears empty.';
        }

        return 'OK: SQL backup file exists and is readable.';
    }

    private function verifyZipFile(string $absolutePath, BackupType $type): string
    {
        $zip = new ZipArchive;
        $result = $zip->open($absolutePath);

        if ($result !== true) {
            return 'ERROR: ZIP archive could not be opened.';
        }

        if ($zip->numFiles <= 0) {
            $zip->close();

            return 'ERROR: ZIP archive contains no files.';
        }

        if ($type === BackupType::Full) {
            $hasDatabase = false;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (is_string($name) && str_starts_with($name, 'database/')) {
                    $hasDatabase = true;
                    break;
                }
            }

            $zip->close();

            if (! $hasDatabase) {
                return 'ERROR: Full backup archive is missing database/backup.sql.';
            }

            return 'OK: Full backup archive is readable and contains database and files.';
        }

        $zip->close();

        return 'OK: ZIP backup archive is readable.';
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }
}
