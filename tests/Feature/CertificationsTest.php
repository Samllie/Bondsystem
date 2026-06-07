<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
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

        $certA = $this->certificateFor($requesterA);
        $this->certificateFor($requesterB);

        $response = $this->actingAs($requesterA)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Certifications/Index')
            ->where('isSuperAdmin', false)
            ->has('certificates.data', 1)
            ->where('certificates.data.0.id', $certA->id)
        );
    }

    public function test_super_admin_sees_certificates_across_all_branches(): void
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
            ->where('isSuperAdmin', true)
            ->has('certificates.data', 2)
        );
    }

    public function test_approver_is_branch_scoped(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $certA = $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchA));
        $this->certificateFor($this->userWithRole(RoleSlug::Requester, $branchB));

        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $response = $this->actingAs($approver)->get(route('certifications.index'));

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

        // Approved but no certificate generated yet.
        $this->approvedBondRequest($requester);

        $response = $this->actingAs($requester)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('certificates.data', 0));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    private function approvedBondRequest(User $creator): BondRequest
    {
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

    private function certificateFor(User $creator): BondRequest
    {
        $bondRequest = $this->approvedBondRequest($creator);
        $bondRequest->update(['certificate_path' => 'private/certificates/fake.pdf']);

        return $bondRequest->fresh();
    }
}
