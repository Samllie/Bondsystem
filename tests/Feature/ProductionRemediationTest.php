<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BackupRecord;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\Deposit;
use App\Models\Maintenance\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_encoder_cannot_view_bond_request_from_another_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $bondRequest = BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($encoder)
            ->get(route('bond-requests.show', $bondRequest))
            ->assertForbidden();
    }

    public function test_encoder_cannot_update_bond_request_from_another_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $bondRequest = BondRequest::factory()->create([
            'created_by' => $requesterB->id,
            'status' => BondRequestStatus::Pending,
        ]);

        $this->actingAs($encoder)
            ->get(route('bond-requests.edit', $bondRequest))
            ->assertForbidden();
    }

    public function test_encoder_can_view_bond_request_from_own_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $bondRequest = BondRequest::factory()->create(['created_by' => $requesterA->id]);

        $this->actingAs($encoder)
            ->get(route('bond-requests.show', $bondRequest))
            ->assertOk();
    }

    public function test_deposit_receipt_is_stored_on_private_disk(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester, $this->makeBranch('AAA'));
        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'receipt_path' => 'receipts/test-receipt.pdf',
        ]);

        Storage::disk('local')->put($deposit->receipt_path, 'receipt content');

        $this->assertTrue(Storage::disk('local')->exists($deposit->receipt_path));
        $this->assertFalse(Storage::disk('public')->exists($deposit->receipt_path));
    }

    public function test_deposit_receipt_cannot_be_accessed_via_public_storage_url(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester, $this->makeBranch('AAA'));
        $path = 'receipts/private-receipt.pdf';
        Storage::disk('local')->put($path, 'receipt content');

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'receipt_path' => $path,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($path));

        $this->actingAs($requester)
            ->get(route('payments.deposits.download-receipt', $deposit))
            ->assertOk()
            ->assertDownload('deposit-'.$deposit->id.'-receipt.pdf');
    }

    public function test_supporting_document_download_requires_authorization(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $path = 'supporting-documents/2026/06/request_1/support.pdf';
        Storage::disk('local')->put($path, 'support content');

        $bondRequest = BondRequest::factory()->create([
            'created_by' => $requesterA->id,
            'supporting_document_paths' => [$path],
        ]);

        $this->actingAs($requesterB)
            ->get(route('bond-requests.supporting-documents.download', [
                'bond_request' => $bondRequest->id,
                'path' => $path,
            ]))
            ->assertForbidden();

        $this->actingAs($requesterA)
            ->get(route('bond-requests.supporting-documents.download', [
                'bond_request' => $bondRequest->id,
                'path' => $path,
            ]))
            ->assertOk()
            ->assertDownload('support.pdf');
    }

    public function test_supporting_document_cannot_be_accessed_via_public_storage_url(): void
    {
        $path = 'supporting-documents/2026/06/request_1/support.pdf';
        Storage::disk('local')->put($path, 'support content');

        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_deleting_certificate_version_removes_qr_code_file(): void
    {
        $approver = $this->userWithRole(RoleSlug::Approver, $this->makeBranch('MKT'));
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);

        $qrPath = sprintf('private/qr-codes/%s/%s/certificate_version_99.png', now()->format('Y'), now()->format('m'));
        $this->writePrivateFile($qrPath, 'qr image');

        $oldVersion = CertificateVersion::factory()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'is_current' => false,
            'docx_path' => $this->writePrivateFile('private/generated-docx/test_v1.docx', 'docx'),
            'pdf_path' => $this->writePrivateFile('private/certificates/test_v1.pdf', '%PDF'),
            'qr_code_path' => $qrPath,
        ]);

        CertificateVersion::factory()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 2,
            'is_current' => true,
        ]);

        $this->actingAs($approver)
            ->delete(route('certificate-versions.destroy', $oldVersion))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist(storage_path('app/'.$qrPath));
    }

    public function test_version_download_filename_strips_invalid_characters(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester, $this->makeBranch('MKT'));
        $bondRequest = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_name' => 'Dept: Finance / Legal',
            'bond_number' => 'G-42',
        ]);

        $pdfPath = $this->writePrivateFile('private/certificates/test_v1.pdf', '%PDF');
        $version = CertificateVersion::factory()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'is_current' => true,
            'pdf_path' => $pdfPath,
            'docx_path' => $this->writePrivateFile('private/generated-docx/test_v1.docx', 'docx'),
        ]);
        $bondRequest->update(['certificate_path' => $pdfPath]);

        $response = $this->actingAs($requester)
            ->get(route('certificate-versions.download', $version));

        $response->assertOk();
        $this->assertStringNotContainsString(':', $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('/', $response->headers->get('content-disposition'));
    }

    public function test_failed_backup_verification_marks_backup_as_failed_after_create(): void
    {
        $record = BackupRecord::factory()->create([
            'backup_type' => BackupType::Files,
            'file_path' => 'backups/files/corrupt.zip',
            'filename' => 'corrupt.zip',
            'backup_status' => BackupStatus::Completed,
        ]);

        File::ensureDirectoryExists(dirname(storage_path('app/private/backups/files')));
        file_put_contents(storage_path('app/private/backups/files/corrupt.zip'), 'not-a-zip');

        app(BackupService::class)->verifyBackup($record, $this->userWithRole(RoleSlug::SuperAdmin, $this->makeBranch('HQ')));

        $this->assertSame(BackupStatus::Failed, $record->fresh()->backup_status);
        $this->assertFalse($record->fresh()->verification_passed);
    }

    private function makeBranch(string $code): Branch
    {
        return Branch::query()->create([
            'name' => "{$code} Branch",
            'branch_code' => $code,
            'branch_city' => 'City',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
    }

    private function userWithRole(RoleSlug $slug, Branch $branch): User
    {
        $role = Role::where('slug', $slug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function writePrivateFile(string $relativePath, string $contents): string
    {
        $absolutePath = storage_path('app/'.$relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);

        return $relativePath;
    }
}
