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

class CertificateAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $testCertPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->testCertPath = storage_path('app/private/certificates/test_certificate.pdf');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCertPath)) {
            @unlink($this->testCertPath);
        }
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // view-certificate
    // -------------------------------------------------------------------------

    public function test_requester_can_view_certificate_for_own_request(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.view-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_requester_cannot_view_certificate_for_another_branch(): void
    {
        $requester = $this->requesterUser();
        $otherRequester = $this->requesterUser();
        $otherRequester->update(['branch_id' => $this->makeBranch('OTH')->id]);
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $otherRequester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.view-certificate', $bondRequest));

        $response->assertForbidden();
    }

    public function test_approver_can_view_certificate_for_any_request(): void
    {
        $approver = $this->approverUser();
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($approver)
            ->get(route('bond-requests.view-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_super_admin_can_view_certificate(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->bondRequestWithCertificate();

        $response = $this->actingAs($superAdmin)
            ->get(route('bond-requests.view-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_view_certificate_returns_404_when_no_certificate(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.view-certificate', $bondRequest));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // download-certificate
    // -------------------------------------------------------------------------

    public function test_requester_can_download_certificate_for_own_request(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_requester_cannot_download_certificate_for_another_branch(): void
    {
        $requester = $this->requesterUser();
        $otherRequester = $this->requesterUser();
        $otherRequester->update(['branch_id' => $this->makeBranch('OTH')->id]);
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $otherRequester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertForbidden();
    }

    public function test_requester_can_download_certificate_from_same_branch_colleague(): void
    {
        $branch = $this->makeBranch('SHR');
        $requester = $this->requesterUser();
        $requester->update(['branch_id' => $branch->id]);
        $colleague = $this->requesterUser();
        $colleague->update(['branch_id' => $branch->id]);
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $colleague);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_approver_can_download_certificate_for_any_request(): void
    {
        $approver = $this->approverUser();
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($approver)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_approver_can_view_and_download_certificate_from_another_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');

        $approver = $this->approverUser();
        $approver->update(['branch_id' => $branchA->id, 'branch_code' => $branchA->branch_code]);

        $otherRequester = $this->requesterUser();
        $otherRequester->update(['branch_id' => $branchB->id, 'branch_code' => $branchB->branch_code]);

        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $otherRequester);

        $this->actingAs($approver)
            ->get(route('bond-requests.view-certificate', $bondRequest))
            ->assertOk();

        $this->actingAs($approver)
            ->get(route('bond-requests.download-certificate', $bondRequest))
            ->assertOk();
    }

    public function test_approver_has_certifications_view_assigned_permission(): void
    {
        $approver = $this->approverUser();

        $this->assertTrue($approver->hasPermission('certifications.view-assigned'));
    }

    public function test_super_admin_can_download_certificate(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->bondRequestWithCertificate();

        $response = $this->actingAs($superAdmin)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertOk();
    }

    public function test_download_certificate_returns_404_when_no_certificate(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // generate-certificate is forbidden for requesters
    // -------------------------------------------------------------------------

    public function test_requester_cannot_generate_certificate(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest(ownedBy: $requester);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);

        $response = $this->actingAs($requester)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Dashboard exposes has_certificate
    // -------------------------------------------------------------------------

    public function test_requester_dashboard_includes_has_certificate_flag(): void
    {
        $requester = $this->requesterUser();
        $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.has_certificate', true)
        );
    }

    // -------------------------------------------------------------------------
    // Show page exposes hasCertificate for requesters
    // -------------------------------------------------------------------------

    public function test_requester_show_page_includes_has_certificate_prop(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $requester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.show', $bondRequest));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BondRequests/Show')
            ->where('hasCertificate', true)
            ->where('canGenerateCertificate', false)
        );
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

    private function requesterUser(): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function approverUser(): User
    {
        $role = Role::where('slug', RoleSlug::Approver->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function superAdminUser(): User
    {
        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function approvedBondRequest(?User $ownedBy = null): BondRequest
    {
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
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

    private function bondRequestWithCertificate(?User $ownedBy = null): BondRequest
    {
        $relativePath = 'private/certificates/test_certificate.pdf';

        if (! is_dir(dirname($this->testCertPath))) {
            mkdir(dirname($this->testCertPath), 0755, true);
        }
        file_put_contents($this->testCertPath, '%PDF-1.4 fake pdf content');

        $bondRequest = $this->approvedBondRequest($ownedBy);
        $bondRequest->update(['certificate_path' => $relativePath]);

        return $bondRequest->fresh();
    }
}
