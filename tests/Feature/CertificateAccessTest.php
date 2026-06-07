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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
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

    public function test_requester_cannot_view_certificate_for_another_users_request(): void
    {
        $requester = $this->requesterUser();
        $otherRequester = $this->requesterUser();
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

    public function test_requester_cannot_download_certificate_for_another_users_request(): void
    {
        $requester = $this->requesterUser();
        $otherRequester = $this->requesterUser();
        $bondRequest = $this->bondRequestWithCertificate(ownedBy: $otherRequester);

        $response = $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertForbidden();
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
        $fakePdfPath = 'private/certificates/test_certificate.pdf';
        Storage::disk('local')->put($fakePdfPath, '%PDF-1.4 fake pdf content');

        $bondRequest = $this->approvedBondRequest($ownedBy);
        $bondRequest->update(['certificate_path' => $fakePdfPath]);

        return $bondRequest->fresh();
    }
}
