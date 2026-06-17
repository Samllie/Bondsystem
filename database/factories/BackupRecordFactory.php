<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\BackupRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRecord>
 */
class BackupRecordFactory extends Factory
{
    protected $model = BackupRecord::class;

    public function definition(): array
    {
        $timestamp = now()->format('Y_m_d_His');
        $type = fake()->randomElement(BackupType::cases());
        $filename = match ($type) {
            BackupType::Database => "backup_{$timestamp}.sql",
            BackupType::Files => "files_{$timestamp}.zip",
            BackupType::Full => "full_backup_{$timestamp}.zip",
        };

        $subdir = match ($type) {
            BackupType::Database => 'database',
            BackupType::Files => 'files',
            BackupType::Full => 'full',
        };

        return [
            'backup_type' => $type,
            'filename' => $filename,
            'file_path' => "backups/{$subdir}/{$filename}",
            'file_size' => fake()->numberBetween(1024, 1048576),
            'backup_status' => BackupStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'created_by' => User::factory(),
            'notes' => null,
            'verification_passed' => true,
            'verified_at' => now(),
            'verification_message' => 'Backup file exists and passed verification.',
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'backup_status' => BackupStatus::Failed,
            'completed_at' => now(),
            'verification_passed' => null,
            'verified_at' => null,
        ]);
    }
}
