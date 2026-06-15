<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_requester_sees_only_their_own_branch_certificates(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $certA = $this->certificateFor($requesterA, $approver);
        $this->certificateFor($requesterB);

        $response = $this->actingAs($requesterA)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Certifications/Index')
            ->where('context', 'user')
            ->where('canViewAllBranches', false)
            ->where('showBranchFilter', false)
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $certA->id)
            ->where('certificates.data.0.requester_name', $requesterA->name)
            ->where('certificates.data.0.approver_name', $approver->name)
        );
    }

    public function test_super_admin_sees_certificates_across_all_branches_on_user_route(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin, $branchA);

        $response = $this->actingAs($superAdmin)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Certifications/Index')
            ->where('context', 'user')
            ->where('canViewAllBranches', true)
            ->where('showBranchFilter', false)
            ->has('certificates.data', 2)
        );
    }

    public function test_approver_sees_certificates_across_all_branches_by_default(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $certA = $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $certB = $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $response = $this->actingAs($approver)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('context', 'user')
            ->where('showBranchFilter', true)
            ->has('certificates.data', 2)
        );
    }

    public function test_approver_can_filter_certifications_by_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $certA = $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $response = $this->actingAs($approver)->get(route('certifications.index', [
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $certA->id)
        );
    }

    public function test_requests_without_a_generated_certificate_are_excluded(): void
    {
        $branch = $this->makeBranch('AAA');
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $this->approvedBondRequest($requester);

        $response = $this->actingAs($requester)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('certificates.data', 0));
    }

    public function test_requester_cannot_access_maintenance_certification_registry(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester, $this->makeBranch('AAA'));

        $this->actingAs($requester)
            ->get(route('maintenance.certifications.index'))
            ->assertForbidden();
    }

    public function test_maintenance_registry_shows_all_branch_certificates(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $response = $this->actingAs($encoder)->get(route('maintenance.certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('context', 'maintenance')
            ->where('canViewAllBranches', true)
            ->where('showBranchFilter', true)
            ->has('certificates.data', 2)
        );
    }

    public function test_maintenance_registry_can_filter_by_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $certA = $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $response = $this->actingAs($encoder)->get(route('maintenance.certifications.index', [
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $certA->id)
        );
    }

    public function test_user_can_search_certifications_by_confirmation_number(): void
    {
        $branch = $this->makeBranch('AAA');
        $approver = $this->userWithRole(RoleSlug::Approver, $branch);
        $cert = $this->certificateFor(
            $this->userWithRole(RoleSlug::Requester, $branch),
            $approver,
            'SICI-BOND-2026-ABCDEF01-V1',
        );
        $this->certificateFor(
            $this->userWithRole(RoleSlug::Requester, $branch),
            $approver,
            'SICI-BOND-2026-99999999-V1',
        );

        $response = $this->actingAs($approver)->get(route('certifications.index', [
            'search' => 'ABCDEF01',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $cert->id)
            ->where('certificates.data.0.confirmation_number', 'SICI-BOND-2026-ABCDEF01-V1')
        );
    }

    public function test_requester_cannot_search_other_branch_certificate_by_confirmation_number(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);

        $this->certificateFor(
            $this->userWithRole(RoleSlug::Requester, $branchB),
            null,
            'SICI-BOND-2026-BBBBBBBB-V1',
        );

        $response = $this->actingAs($requesterA)->get(route('certifications.index', [
            'search' => 'BBBBBBBB',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('certificates.data', 0));
    }

    public function test_user_can_search_certifications_by_verification_token(): void
    {
        $branch = $this->makeBranch('AAA');
        $approver = $this->userWithRole(RoleSlug::Approver, $branch);
        $token = bin2hex(random_bytes(32));
        $cert = $this->certificateFor(
            $this->userWithRole(RoleSlug::Requester, $branch),
            $approver,
            'SICI-BOND-2026-TOKEN0001-V1',
        );
        $cert->currentCertificateVersion->update(['verification_token' => $token]);

        $response = $this->actingAs($approver)->get(route('certifications.index', [
            'search' => $token,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $cert->id)
        );
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

    private function certificateFor(User $creator, ?User $approver = null, ?string $confirmationNumber = null): BondRequest
    {
        $bondRequest = $this->approvedBondRequest($creator, $approver);
        $bondRequest->update(['certificate_path' => 'private/certificates/fake.pdf']);

        CertificateVersion::factory()->current()->create([
            'bond_request_id' => $bondRequest->id,
            'generated_by' => ($approver ?? $creator)->id,
            'confirmation_number' => $confirmationNumber ?? 'SICI-BOND-2026-'.strtoupper(bin2hex(random_bytes(4))).'-V1',
        ]);

        return $bondRequest->fresh();
    }

    private function approvedBondRequest(User $creator, ?User $approver = null): BondRequest
    {
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);
        $approver ??= $this->userWithRole(RoleSlug::Approver, Branch::query()->findOrFail($creator->branch_id));

        return BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'created_by' => $creator->id,
                'approved_by' => $approver->id,
                'tin' => '123-456-789-0000',
            ]);
    }
}
