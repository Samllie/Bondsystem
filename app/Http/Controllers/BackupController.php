<?php

namespace App\Http\Controllers;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\BackupRecord;
use App\Services\AuditLogService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BackupRecord::class);

        $backups = BackupRecord::query()
            ->with('creator:id,name')
            ->when($request->input('backup_type'), fn ($query, $type) => $query->where('backup_type', $type))
            ->when($request->input('backup_status'), fn ($query, $status) => $query->where('backup_status', $status))
            ->latest('started_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BackupRecord $backup) => [
                'id' => $backup->id,
                'backup_type' => $backup->backup_type?->value,
                'backup_type_label' => $backup->backup_type?->label(),
                'filename' => $backup->filename,
                'file_size' => $backup->file_size,
                'backup_status' => $backup->backup_status?->value,
                'backup_status_label' => $backup->backup_status?->label(),
                'started_at' => $backup->started_at?->toIso8601String(),
                'completed_at' => $backup->completed_at?->toIso8601String(),
                'created_by' => $backup->creator?->name,
                'notes' => $backup->notes,
                'verification_passed' => $backup->verification_passed,
                'verified_at' => $backup->verified_at?->toIso8601String(),
                'verification_message' => $backup->verification_message,
                'can_download' => $backup->isCompleted() && file_exists($backup->absolutePath()),
            ]);

        return Inertia::render('Backups/Index', [
            'backups' => $backups,
            'filters' => $request->only('backup_type', 'backup_status'),
            'typeOptions' => BackupType::options(),
            'statusOptions' => BackupStatus::options(),
            'restoreInstructions' => config('backups.restore_instructions', []),
            'scheduleExamples' => config('backups.schedule_examples', []),
            'retentionDays' => (int) config('backups.keep_days', 30),
        ]);
    }

    public function show(BackupRecord $backup): Response
    {
        Gate::authorize('view', $backup);

        $backup->load('creator:id,name');

        return Inertia::render('Backups/Show', [
            'backup' => [
                'id' => $backup->id,
                'backup_type' => $backup->backup_type?->value,
                'backup_type_label' => $backup->backup_type?->label(),
                'filename' => $backup->filename,
                'file_path' => $backup->file_path,
                'file_size' => $backup->file_size,
                'backup_status' => $backup->backup_status?->value,
                'backup_status_label' => $backup->backup_status?->label(),
                'started_at' => $backup->started_at?->toIso8601String(),
                'completed_at' => $backup->completed_at?->toIso8601String(),
                'created_by' => $backup->creator?->name,
                'notes' => $backup->notes,
                'verification_passed' => $backup->verification_passed,
                'verified_at' => $backup->verified_at?->toIso8601String(),
                'verification_message' => $backup->verification_message,
                'can_download' => $backup->isCompleted() && file_exists($backup->absolutePath()),
            ],
            'restoreInstructions' => config('backups.restore_instructions', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', BackupRecord::class);

        $validated = $request->validate([
            'backup_type' => ['required', 'in:database,files,full'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = BackupType::from($validated['backup_type']);
        $user = $request->user();
        $notes = $validated['notes'] ?? null;

        $record = match ($type) {
            BackupType::Database => $this->backupService->createDatabaseBackup($user, $notes),
            BackupType::Files => $this->backupService->createFilesBackup($user, $notes),
            BackupType::Full => $this->backupService->createFullBackup($user, $notes),
        };

        if ($record->backup_status === BackupStatus::Failed) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup failed: '.($record->verification_message ?? 'Unknown error.'));
        }

        return redirect()
            ->route('backups.show', $record)
            ->with('success', 'Backup created successfully.');
    }

    public function download(BackupRecord $backup): StreamedResponse
    {
        Gate::authorize('download', $backup);

        abort_unless($backup->isCompleted(), 404, 'Backup is not available for download.');
        abort_unless(file_exists($backup->absolutePath()), 404, 'Backup file not found.');

        AuditLogService::log(
            user: request()->user(),
            action: 'backup_downloaded',
            entityType: AuditLogService::ENTITY_BACKUP,
            entityId: $backup->id,
            newValues: [
                'backup_type' => $backup->backup_type?->value,
                'filename' => $backup->filename,
            ],
            description: "Backup {$backup->filename} downloaded.",
        );

        return response()->streamDownload(function () use ($backup): void {
            $stream = fopen($backup->absolutePath(), 'rb');

            if ($stream === false) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, $backup->filename);
    }

    public function verify(BackupRecord $backup): RedirectResponse
    {
        Gate::authorize('verify', $backup);

        $passed = $this->backupService->verifyBackup($backup, request()->user());

        return redirect()
            ->route('backups.show', $backup)
            ->with($passed ? 'success' : 'error', $passed
                ? 'Backup verification passed.'
                : ($backup->fresh()->verification_message ?? 'Backup verification failed.'));
    }

    public function destroy(BackupRecord $backup): RedirectResponse
    {
        Gate::authorize('delete', $backup);

        if (! $this->backupService->deleteBackup($backup, request()->user())) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Unable to delete backup.');
        }

        return redirect()
            ->route('backups.index')
            ->with('success', 'Backup deleted successfully.');
    }
}
