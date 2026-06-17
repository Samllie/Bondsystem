<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\RoleSlug;
use App\Models\BackupRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seedSampleProtectedFile();
    }

    public function test_database_backup_creation(): void
    {
        $record = app(BackupService::class)->createDatabaseBackup($this->superAdmin());

        $this->assertSame(BackupType::Database, $record->backup_type);
        $this->assertSame(BackupStatus::Completed, $record->backup_status);
        $this->assertFileExists($record->absolutePath());
        $this->assertGreaterThan(0, $record->file_size);
        $this->assertTrue($record->verification_passed);
    }

    public function test_files_backup_creation(): void
    {
        $record = app(BackupService::class)->createFilesBackup($this->superAdmin());

        $this->assertSame(BackupType::Files, $record->backup_type);
        $this->assertSame(BackupStatus::Completed, $record->backup_status);
        $this->assertFileExists($record->absolutePath());
        $this->assertTrue(str_ends_with($record->filename, '.zip'));
    }

    public function test_full_backup_creation(): void
    {
        $record = app(BackupService::class)->createFullBackup($this->superAdmin());

        $this->assertSame(BackupType::Full, $record->backup_type);
        $this->assertSame(BackupStatus::Completed, $record->backup_status);
        $this->assertFileExists($record->absolutePath());

        $zip = new \ZipArchive;
        $zip->open($record->absolutePath());
        $this->assertNotFalse($zip->locateName('database/backup.sql'));
        $zip->close();
    }

    public function test_backup_record_is_created_when_using_artisan_command(): void
    {
        $this->artisan('backups:create database')->assertSuccessful();

        $this->assertDatabaseHas('backup_records', [
            'backup_type' => BackupType::Database->value,
            'backup_status' => BackupStatus::Completed->value,
        ]);
    }

    public function test_backup_verification_detects_missing_file(): void
    {
        $record = BackupRecord::factory()->create([
            'backup_type' => BackupType::Database,
            'file_path' => 'backups/database/missing.sql',
            'filename' => 'missing.sql',
            'backup_status' => BackupStatus::Completed,
        ]);

        $passed = app(BackupService::class)->verifyBackup($record, $this->superAdmin());

        $this->assertFalse($passed);
        $this->assertFalse($record->fresh()->verification_passed);
        $this->assertSame(BackupStatus::Failed, $record->fresh()->backup_status);
    }

    public function test_super_admin_can_download_completed_backup(): void
    {
        $admin = $this->superAdmin();
        $record = app(BackupService::class)->createDatabaseBackup($admin);

        $response = $this->actingAs($admin)->get(route('backups.download', $record));

        $response->assertOk();
        $response->assertDownload($record->filename);
    }

    public function test_super_admin_can_delete_backup(): void
    {
        $admin = $this->superAdmin();
        $record = app(BackupService::class)->createDatabaseBackup($admin);
        $path = $record->absolutePath();

        $response = $this->actingAs($admin)->delete(route('backups.destroy', $record));

        $response->assertRedirect(route('backups.index'));
        $this->assertDatabaseMissing('backup_records', ['id' => $record->id]);
        $this->assertFileDoesNotExist($path);
    }

    public function test_cleanup_command_deletes_old_completed_backups_only(): void
    {
        config(['backups.keep_days' => 30]);

        $oldRecord = app(BackupService::class)->createDatabaseBackup(null, 'old');
        $oldRecord->update(['completed_at' => now()->subDays(31)]);

        $recentRecord = app(BackupService::class)->createDatabaseBackup(null, 'recent');

        $failedRecord = BackupRecord::factory()->failed()->create([
            'completed_at' => now()->subDays(60),
            'file_path' => 'backups/database/failed.sql',
            'filename' => 'failed.sql',
        ]);
        File::ensureDirectoryExists(dirname(storage_path('app/private/backups/database')));
        file_put_contents(storage_path('app/private/backups/database/failed.sql'), '-- failed');

        Artisan::call('backups:cleanup');

        $this->assertDatabaseMissing('backup_records', ['id' => $oldRecord->id]);
        $this->assertDatabaseHas('backup_records', ['id' => $recentRecord->id]);
        $this->assertDatabaseHas('backup_records', ['id' => $failedRecord->id]);
    }

    public function test_super_admin_can_access_backup_management_page(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('backups.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Backups/Index'));
    }

    public function test_requester_cannot_access_backup_management(): void
    {
        $this->actingAs($this->userWithRole(RoleSlug::Requester))
            ->get(route('backups.index'))
            ->assertForbidden();
    }

    public function test_approver_cannot_access_backup_management(): void
    {
        $this->actingAs($this->userWithRole(RoleSlug::Approver))
            ->get(route('backups.index'))
            ->assertForbidden();
    }

    public function test_audit_logs_are_created_for_backup_actions(): void
    {
        $admin = $this->superAdmin();
        $record = app(BackupService::class)->createDatabaseBackup($admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'backup_created',
            'entity_type' => AuditLogService::ENTITY_BACKUP,
            'entity_id' => $record->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('backups.download', $record))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'backup_downloaded',
            'entity_type' => AuditLogService::ENTITY_BACKUP,
            'entity_id' => $record->id,
        ]);
    }

    private function superAdmin(): User
    {
        return $this->userWithRole(RoleSlug::SuperAdmin);
    }

    private function userWithRole(RoleSlug $slug): User
    {
        $role = Role::where('slug', $slug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function seedSampleProtectedFile(): void
    {
        $directory = storage_path('app/public/backup-test');
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/sample.txt', 'backup test file');
    }
}
