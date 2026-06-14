<?php

namespace Tests\Feature;

use App\Enums\CertificateTemplateType;
use App\Enums\CertificateType;
use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BondRequest;
use App\Models\CertificateTemplate;
use App\Models\Deposit;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CertificateGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private string $testCertPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->testCertPath = storage_path('app/private/certificates/audit_test_certificate.pdf');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCertPath)) {
            @unlink($this->testCertPath);
        }

        parent::tearDown();
    }

    public function test_audit_log_created_after_certificate_generation(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequestForGeneration();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $approver->id,
            'action' => 'certificate_generated',
            'entity_type' => AuditLogService::ENTITY_BOND_REQUEST,
            'entity_id' => $bondRequest->id,
        ]);
    }

    public function test_audit_log_created_after_certificate_download(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate($requester);

        $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $requester->id,
            'action' => 'certificate_downloaded',
            'entity_type' => AuditLogService::ENTITY_BOND_REQUEST,
            'entity_id' => $bondRequest->id,
        ]);
    }

    public function test_audit_log_created_after_receipt_approval(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, branchBalance: 1000);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)
            ->post(route('payments.deposits.approve', $deposit))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $approver->id,
            'action' => 'receipt_approved',
            'entity_type' => AuditLogService::ENTITY_RECEIPT,
            'entity_id' => $deposit->id,
        ]);
    }

    public function test_audit_log_created_after_template_activation(): void
    {
        Storage::fake('local');

        $admin = $this->superAdminUser();
        $first = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1, active: true);
        $second = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 2);

        $this->actingAs($admin)
            ->patch(route('certificate-templates.activate', $second))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'template_activated',
            'entity_type' => AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            'entity_id' => $second->id,
        ]);

        $this->assertFalse($first->fresh()->is_active);
    }

    public function test_audit_log_captures_user_id(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->actingAs($approver)
            ->post(route('bond-requests.approve', $bondRequest), [
                'signatory_id' => $bondRequest->signatory_id,
                'notary_id' => $bondRequest->notary_id,
                'series_year' => '2026',
            ])
            ->assertRedirect();

        $log = AuditLog::query()->where('action', 'bond_request_approved')->first();

        $this->assertNotNull($log);
        $this->assertSame($approver->id, $log->user_id);
    }

    public function test_audit_log_captures_route_name(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->actingAs($approver)
            ->post(route('bond-requests.reject', $bondRequest), ['remarks' => 'Incomplete documents'])
            ->assertRedirect();

        $log = AuditLog::query()->where('action', 'bond_request_rejected')->first();

        $this->assertNotNull($log);
        $this->assertSame('bond-requests.reject', $log->route_name);
    }

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = $this->superAdminUser();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'bond_request_created',
            'entity_type' => AuditLogService::ENTITY_BOND_REQUEST,
            'entity_id' => 1,
            'description' => 'Test log entry.',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AuditLogs/Index')
                ->has('logs.data', 1)
                ->has('actionOptions')
                ->has('entityTypeOptions')
            );
    }

    public function test_requester_cannot_access_audit_logs(): void
    {
        $requester = $this->requesterUser();

        $this->actingAs($requester)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_approver_cannot_access_audit_logs(): void
    {
        $approver = $this->approverUser();

        $this->actingAs($approver)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_filters_work_correctly(): void
    {
        $admin = $this->superAdminUser();
        $approver = $this->approverUser();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'template_activated',
            'entity_type' => AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            'entity_id' => 10,
            'description' => 'Template activated.',
            'created_at' => now()->subDays(2),
        ]);

        AuditLog::query()->create([
            'user_id' => $approver->id,
            'action' => 'bond_request_approved',
            'entity_type' => AuditLogService::ENTITY_BOND_REQUEST,
            'entity_id' => 20,
            'description' => 'Bond approved.',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index', [
                'user_id' => $approver->id,
                'action' => 'bond_request_approved',
                'entity_type' => AuditLogService::ENTITY_BOND_REQUEST,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AuditLogs/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'bond_request_approved')
                ->where('logs.data.0.entity_type', AuditLogService::ENTITY_BOND_REQUEST)
                ->where('logs.data.0.user.id', $approver->id)
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(RoleSlug $roleSlug, array $attributes = [], ?Branch $branch = null, float $branchBalance = 0): User
    {
        $role = Role::where('slug', $roleSlug->value)->firstOrFail();

        $branch ??= Branch::query()->create([
            'name' => 'Test Branch',
            'branch_code' => strtoupper(Str::random(3)),
            'address' => 'Branch City',
            'notary_price' => 500,
            'balance' => $branchBalance,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'branch_city' => 'Branch City',
            'balance' => 0,
            'is_active' => true,
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }

    private function approverUser(): User
    {
        return $this->createUser(RoleSlug::Approver);
    }

    private function requesterUser(): User
    {
        return $this->createUser(RoleSlug::Requester);
    }

    private function superAdminUser(): User
    {
        return $this->createUser(RoleSlug::SuperAdmin);
    }

    private function approvedBondRequest(?User $ownedBy = null): BondRequest
    {
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $creator = $ownedBy ?? User::factory()->create(['branch_id' => $branch->id]);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);

        return BondRequest::factory()
            ->pending()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'created_by' => $creator->id,
                'tin' => '123-456-789-0000',
            ]);
    }

    private function approvedBondRequestForGeneration(?User $ownedBy = null): BondRequest
    {
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $creator = $ownedBy ?? User::factory()->create(['branch_id' => $branch->id]);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);

        return BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'created_by' => $creator->id,
                'tin' => '123-456-789-0000',
            ]);
    }

    private function bondRequestWithCertificate(User $ownedBy): BondRequest
    {
        $relativePath = 'private/certificates/audit_test_certificate.pdf';

        if (! is_dir(dirname($this->testCertPath))) {
            mkdir(dirname($this->testCertPath), 0755, true);
        }
        file_put_contents($this->testCertPath, '%PDF-1.4 fake pdf content');

        $bondRequest = BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'created_by' => $ownedBy->id,
                'certificate_path' => $relativePath,
            ]);

        return $bondRequest->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validGeneratePayload(BondRequest $bondRequest): array
    {
        return [
            'signatory_id' => $bondRequest->signatory_id,
            'notary_id' => $bondRequest->notary_id,
            'doc_no' => '1',
            'page_no' => '1',
            'book_no' => 'I',
            'series_year' => '2026',
        ];
    }

    private function createStoredTemplate(
        User $admin,
        CertificateTemplateType $type,
        int $version,
        bool $active = false,
    ): CertificateTemplate {
        $storedPath = "certificate-templates/{$type->value}_v{$version}_audit.docx";
        Storage::disk('local')->put($storedPath, 'PK'.str_repeat("\0", 100));

        return CertificateTemplate::factory()->create([
            'template_type' => $type,
            'template_name' => "{$type->label()} Template {$version}",
            'version' => $version,
            'file_path' => $storedPath,
            'original_filename' => "{$type->value}-{$version}.docx",
            'file_size' => Storage::disk('local')->size($storedPath),
            'uploaded_by' => $admin->id,
            'is_active' => $active,
        ]);
    }
}
