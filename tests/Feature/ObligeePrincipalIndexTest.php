<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\Maintenance\Branch;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Services\KycObligeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ObligeePrincipalIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_obligees_index_uses_kyc_data(): void
    {
        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin);

        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('paginate')
                ->once()
                ->with(null)
                ->andReturn(new LengthAwarePaginator([
                    [
                        'id' => 101,
                        'company_name' => 'Department of Public Works',
                        'label' => 'Department of Public Works',
                        'contact_person' => 'Jane Doe',
                        'email' => 'dpwh@example.com',
                    ],
                ], 1, 10));
        });

        $this->actingAs($superAdmin)
            ->get(route('obligees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Obligees/Index')
                ->where('kycView', true)
                ->has('kycObligees.data', 1)
                ->where('kycObligees.data.0.id', 101)
                ->where('kycObligees.data.0.company_name', 'Department of Public Works')
            );
    }

    public function test_super_admin_obligees_index_shows_certificate_obligees_by_source(): void
    {
        $branch = $this->makeBranch('OBL');
        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin, $branch);
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('paginate')
                ->once()
                ->andReturn(new LengthAwarePaginator([], 0, 10));
        });

        $kycBondA = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_id' => 501,
            'obligee_name' => 'Department of Public Works',
        ]);
        $kycBondB = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_id' => 501,
            'obligee_name' => 'Department of Public Works',
        ]);
        $typedBond = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_id' => null,
            'obligee_name' => 'Custom Typed Obligee',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $kycBondA->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $kycBondB->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $typedBond->id]);

        BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_id' => null,
            'obligee_name' => 'No Certificate Yet',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('obligees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Obligees/Index')
                ->where('kycView', true)
                ->has('certificateObligeesFromKyc.data', 1)
                ->where('certificateObligeesFromKyc.data.0.obligee_id', 501)
                ->where('certificateObligeesFromKyc.data.0.company_name', 'Department of Public Works')
                ->where('certificateObligeesFromKyc.data.0.certificates_count', 2)
                ->has('certificateObligeesTyped.data', 1)
                ->where('certificateObligeesTyped.data.0.company_name', 'Custom Typed Obligee')
                ->where('certificateObligeesTyped.data.0.certificates_count', 1)
            );
    }

    public function test_approver_obligees_index_uses_local_records(): void
    {
        $approver = $this->userWithRole(RoleSlug::Approver);

        Obligee::factory()->create([
            'company_name' => 'Local Obligee Corp',
        ]);

        $this->actingAs($approver)
            ->get(route('obligees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Obligees/Index')
                ->where('kycView', false)
                ->where('branchConfirmationsView', false)
                ->has('obligees.data', 1)
                ->where('obligees.data.0.company_name', 'Local Obligee Corp')
            );
    }

    public function test_requester_obligees_index_shows_branch_certificate_obligees(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);

        $branchABond = BondRequest::factory()->approved()->create([
            'created_by' => $requesterA->id,
            'obligee_id' => 501,
            'obligee_name' => 'Branch A Obligee',
        ]);
        $branchATypedBond = BondRequest::factory()->approved()->create([
            'created_by' => $requesterA->id,
            'obligee_id' => null,
            'obligee_name' => 'Branch A Typed Obligee',
        ]);
        $branchBBond = BondRequest::factory()->approved()->create([
            'created_by' => $requesterB->id,
            'obligee_id' => 502,
            'obligee_name' => 'Branch B Obligee',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $branchABond->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $branchATypedBond->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $branchBBond->id]);

        $this->actingAs($requesterA)
            ->get(route('obligees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Obligees/Index')
                ->where('branchConfirmationsView', true)
                ->where('branchName', 'AAA Branch')
                ->has('certificateObligeesFromKyc.data', 1)
                ->where('certificateObligeesFromKyc.data.0.company_name', 'Branch A Obligee')
                ->has('certificateObligeesTyped.data', 1)
                ->where('certificateObligeesTyped.data.0.company_name', 'Branch A Typed Obligee')
            );
    }

    public function test_encoder_obligees_index_shows_branch_certificate_obligees(): void
    {
        $branch = $this->makeBranch('ENC');
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branch);
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $bondRequest = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'obligee_id' => 601,
            'obligee_name' => 'Encoder Branch Obligee',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $bondRequest->id]);

        $this->actingAs($encoder)
            ->get(route('obligees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Obligees/Index')
                ->where('branchConfirmationsView', true)
                ->has('certificateObligeesFromKyc.data', 1)
                ->where('certificateObligeesFromKyc.data.0.company_name', 'Encoder Branch Obligee')
            );
    }

    public function test_super_admin_principals_index_shows_generated_certificate_principals(): void
    {
        $branch = $this->makeBranch('PRN');
        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin, $branch);
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $bondRequestA = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'principal_name' => 'Alpha Construction Inc.',
        ]);
        $bondRequestB = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'principal_name' => 'Alpha Construction Inc.',
        ]);
        $bondRequestC = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'principal_name' => 'Beta Trading Corp.',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $bondRequestA->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $bondRequestB->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $bondRequestC->id]);

        BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'principal_name' => 'No Certificate Yet LLC',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('principals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Principals/Index')
                ->where('generatedCertificatesView', true)
                ->has('principals.data', 2)
                ->where('principals.data.0.company_name', 'Alpha Construction Inc.')
                ->where('principals.data.0.certificates_count', 2)
                ->where('principals.data.1.company_name', 'Beta Trading Corp.')
                ->where('principals.data.1.certificates_count', 1)
            );
    }

    public function test_approver_principals_index_uses_local_records(): void
    {
        $approver = $this->userWithRole(RoleSlug::Approver);

        Principal::factory()->create([
            'company_name' => 'Local Principal Corp',
        ]);

        $this->actingAs($approver)
            ->get(route('principals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Principals/Index')
                ->where('generatedCertificatesView', false)
                ->has('principals.data', 1)
                ->where('principals.data.0.company_name', 'Local Principal Corp')
            );
    }

    public function test_requester_principals_index_shows_branch_certificate_principals(): void
    {
        $branchA = $this->makeBranch('PRA');
        $branchB = $this->makeBranch('PRB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);

        $branchABond = BondRequest::factory()->approved()->create([
            'created_by' => $requesterA->id,
            'principal_name' => 'Branch A Principal Inc.',
        ]);
        $branchBBond = BondRequest::factory()->approved()->create([
            'created_by' => $requesterB->id,
            'principal_name' => 'Branch B Principal Inc.',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $branchABond->id]);
        CertificateVersion::factory()->create(['bond_request_id' => $branchBBond->id]);

        $this->actingAs($requesterA)
            ->get(route('principals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Principals/Index')
                ->where('generatedCertificatesView', true)
                ->where('branchName', 'PRA Branch')
                ->has('principals.data', 1)
                ->where('principals.data.0.company_name', 'Branch A Principal Inc.')
            );
    }

    public function test_encoder_principals_index_shows_branch_certificate_principals(): void
    {
        $branch = $this->makeBranch('PRE');
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branch);
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $bondRequest = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'principal_name' => 'Encoder Branch Principal Inc.',
        ]);

        CertificateVersion::factory()->create(['bond_request_id' => $bondRequest->id]);

        $this->actingAs($encoder)
            ->get(route('principals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Principals/Index')
                ->where('generatedCertificatesView', true)
                ->where('branchName', 'PRE Branch')
                ->has('principals.data', 1)
                ->where('principals.data.0.company_name', 'Encoder Branch Principal Inc.')
            );
    }

    private function userWithRole(RoleSlug $role, ?Branch $branch = null): User
    {
        $branch ??= $this->makeBranch('TST');

        return User::factory()->create([
            'role_id' => Role::where('slug', $role->value)->firstOrFail()->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function makeBranch(string $code): Branch
    {
        return Branch::query()->create([
            'name' => "{$code} Branch",
            'branch_code' => $code,
            'branch_city' => 'City',
            'is_active' => true,
        ]);
    }
}
