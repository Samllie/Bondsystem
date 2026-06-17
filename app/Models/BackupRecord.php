<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Database\Factories\BackupRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    /** @use HasFactory<BackupRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'backup_type',
        'filename',
        'file_path',
        'file_size',
        'backup_status',
        'started_at',
        'completed_at',
        'created_by',
        'notes',
        'verification_passed',
        'verified_at',
        'verification_message',
    ];

    protected function casts(): array
    {
        return [
            'backup_type' => BackupType::class,
            'backup_status' => BackupStatus::class,
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_passed' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function absolutePath(): string
    {
        return storage_path('app/private/'.$this->file_path);
    }

    public function isCompleted(): bool
    {
        return $this->backup_status === BackupStatus::Completed;
    }
}
