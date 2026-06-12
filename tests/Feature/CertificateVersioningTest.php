<?php

namespace Tests\Feature;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificateGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_first_certificate_generation_creates_version_one(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $this->mockSuccessfulGeneration($bondRequest, $approver);

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('certificate_versions', [
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'is_current' => true,
            'generated_by' => $approver->id,
        ]);
    }

    public function test_second_generation_creates_version_two(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $this->createStoredVersion($bondRequest, 1, $approver, isCurrent: true);
        $bondRequest->update([
            'certificate_path' => $this->versionPdfPath($bondRequest, 1),
            'docx_path' => $this->versionDocxPath($bondRequest, 1),
        ]);

        $this->mockSuccessfulGeneration($bondRequest, $approver, 2);

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('certificate_versions', [
            'bond_request_id' => $bondRequest->id,
            'version_number' => 2,
            'is_current' => true,
        ]);
    }

    public function test_previous_version_becomes_not_current_after_regeneration(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $firstVersion = $this->createStoredVersion($bondRequest, 1, $approver, isCurrent: true);

        $this->mockSuccessfulGeneration($bondRequest, $approver, 2);

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $this->assertFalse($firstVersion->fresh()->is_current);
    }

    public function test_newest_version_becomes_current(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $this->createStoredVersion($bondRequest, 1, $approver, isCurrent: true);

        $this->mockSuccessfulGeneration($bondRequest, $approver, 2);

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $current = CertificateVersion::query()
            ->where('bond_request_id', $bondRequest->id)
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($current);
        $this->assertSame(2, $current->version_number);
    }

    public function test_bond_request_certificate_path_points_to_current_version_pdf(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $expectedPdf = $this->versionPdfPath($bondRequest, 1);

        $this->mock(CertificateGenerationService::class, function ($mock) use ($expectedPdf): void {
            $mock->shouldReceive('generate')
                ->once()
                ->with(\Mockery::type(BondRequest::class), \Mockery::type(User::class))
                ->andReturnUsing(function (BondRequest $request, User $user) use ($expectedPdf): void {
                    CertificateVersion::query()
                        ->where('bond_request_id', $request->id)
                        ->update(['is_current' => false]);

                    CertificateVersion::create([
                        'bond_request_id' => $request->id,
                        'version_number' => 1,
                        'certificate_type' => $request->certificate_type,
                        'docx_path' => $this->versionDocxPath($request, 1),
                        'pdf_path' => $expectedPdf,
                        'generated_by' => $user->id,
                        'generated_at' => now(),
                        'is_current' => true,
                    ]);

                    $request->update([
                        'certificate_path' => $expectedPdf,
                        'docx_path' => $this->versionDocxPath($request, 1),
                    ]);
                });
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $this->assertSame($expectedPdf, $bondRequest->fresh()->certificate_path);
    }

    public function test_previous_versions_remain_downloadable(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest($requester);
        $oldVersion = $this->createStoredVersion($bondRequest, 1, $this->approverUser(), isCurrent: false);
        $this->createStoredVersion($bondRequest, 2, $this->approverUser(), isCurrent: true);
        $bondRequest->update(['certificate_path' => $this->versionPdfPath($bondRequest, 2)]);

        $response = $this->actingAs($requester)
            ->get(route('certificate-versions.download', $oldVersion));

        $response->assertOk();
    }

    public function test_requester_can_view_own_certificate_versions(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest($requester);
        $version = $this->createStoredVersion($bondRequest, 1, $this->approverUser());

        $response = $this->actingAs($requester)
            ->get(route('certificate-versions.view', $version));

        $response->assertOk();
    }

    public function test_requester_cannot_view_another_branch_certificate_versions(): void
    {
        $requester = $this->requesterUser();
        $otherRequester = $this->requesterUser();
        $otherRequester->update(['branch_id' => $this->makeBranch('OTH')->id]);
        $bondRequest = $this->approvedBondRequest($otherRequester);
        $version = $this->createStoredVersion($bondRequest, 1, $this->approverUser());

        $response = $this->actingAs($requester)
            ->get(route('certificate-versions.view', $version));

        $response->assertForbidden();
    }

    public function test_super_admin_can_make_older_version_current(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->approvedBondRequest();
        $oldVersion = $this->createStoredVersion($bondRequest, 1, $this->approverUser(), isCurrent: false);
        $this->createStoredVersion($bondRequest, 2, $this->approverUser(), isCurrent: true);
        $bondRequest->update([
            'certificate_path' => $this->versionPdfPath($bondRequest, 2),
            'docx_path' => $this->versionDocxPath($bondRequest, 2),
        ]);

        $response = $this->actingAs($superAdmin)
            ->patch(route('certificate-versions.make-current', $oldVersion));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($oldVersion->fresh()->is_current);
    }

    public function test_making_older_version_current_updates_bond_request_certificate_path(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->approvedBondRequest();
        $oldVersion = $this->createStoredVersion($bondRequest, 1, $this->approverUser(), isCurrent: false);
        $this->createStoredVersion($bondRequest, 2, $this->approverUser(), isCurrent: true);
        $bondRequest->update(['certificate_path' => $this->versionPdfPath($bondRequest, 2)]);

        $this->actingAs($superAdmin)
            ->patch(route('certificate-versions.make-current', $oldVersion));

        $this->assertSame($this->versionPdfPath($bondRequest, 1), $bondRequest->fresh()->certificate_path);
        $this->assertSame($this->versionDocxPath($bondRequest, 1), $bondRequest->fresh()->docx_path);
    }

    public function test_only_one_current_version_exists_per_bond_request(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->approvedBondRequest();
        $versionOne = $this->createStoredVersion($bondRequest, 1, $this->approverUser(), isCurrent: false);
        $versionTwo = $this->createStoredVersion($bondRequest, 2, $this->approverUser(), isCurrent: true);
        $bondRequest->update(['certificate_path' => $this->versionPdfPath($bondRequest, 2)]);

        $this->actingAs($superAdmin)
            ->patch(route('certificate-versions.make-current', $versionOne));

        $currentCount = CertificateVersion::query()
            ->where('bond_request_id', $bondRequest->id)
            ->where('is_current', true)
            ->count();

        $this->assertSame(1, $currentCount);
        $this->assertFalse($versionTwo->fresh()->is_current);
    }

    public function test_failed_generation_does_not_create_current_version(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new \RuntimeException('Template not found'));
        });

        $signatory = $bondRequest->signatory;

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('certificate_versions', 0);
        $this->assertNull($bondRequest->fresh()->certificate_path);
    }

    public function test_existing_current_certificate_buttons_still_work(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest($requester);
        $pdfPath = $this->versionPdfPath($bondRequest, 1);
        $this->writeFile($pdfPath, '%PDF-1.4 test');
        $bondRequest->update(['certificate_path' => $pdfPath]);
        $this->createStoredVersion($bondRequest, 1, $this->approverUser());

        $this->actingAs($requester)
            ->get(route('bond-requests.view-certificate', $bondRequest))
            ->assertOk();

        $this->actingAs($requester)
            ->get(route('bond-requests.download-certificate', $bondRequest))
            ->assertOk();
    }

    public function test_approver_cannot_make_older_version_current(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();
        $oldVersion = $this->createStoredVersion($bondRequest, 1, $approver, isCurrent: false);
        $this->createStoredVersion($bondRequest, 2, $approver, isCurrent: true);

        $this->actingAs($approver)
            ->patch(route('certificate-versions.make-current', $oldVersion))
            ->assertForbidden();
    }

    public function test_show_page_includes_certificate_versions(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest($requester);
        $this->createStoredVersion($bondRequest, 1, $this->approverUser());
        $bondRequest->update(['certificate_path' => $this->versionPdfPath($bondRequest, 1)]);

        $this->actingAs($requester)
            ->get(route('bond-requests.show', $bondRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BondRequests/Show')
                ->has('certificateVersions', 1)
                ->where('certificateVersions.0.version_number', 1)
            );
    }

    private function mockSuccessfulGeneration(BondRequest $bondRequest, User $generatedBy, int $versionNumber = 1): void
    {
        $pdfPath = $this->versionPdfPath($bondRequest, $versionNumber);
        $docxPath = $this->versionDocxPath($bondRequest, $versionNumber);

        $this->mock(CertificateGenerationService::class, function ($mock) use ($versionNumber, $pdfPath, $docxPath): void {
            $mock->shouldReceive('generate')
                ->once()
                ->with(\Mockery::type(BondRequest::class), \Mockery::type(User::class))
                ->andReturnUsing(function (BondRequest $request, User $user) use ($versionNumber, $pdfPath, $docxPath): void {
                    CertificateVersion::query()
                        ->where('bond_request_id', $request->id)
                        ->update(['is_current' => false]);

                    CertificateVersion::create([
                        'bond_request_id' => $request->id,
                        'version_number' => $versionNumber,
                        'certificate_type' => $request->certificate_type,
                        'docx_path' => $docxPath,
                        'pdf_path' => $pdfPath,
                        'generated_by' => $user->id,
                        'generated_at' => now(),
                        'is_current' => true,
                    ]);

                    $request->update([
                        'certificate_path' => $pdfPath,
                        'docx_path' => $docxPath,
                    ]);
                });
        });
    }

    private function createStoredVersion(
        BondRequest $bondRequest,
        int $versionNumber,
        User $generatedBy,
        bool $isCurrent = true,
    ): CertificateVersion {
        $pdfPath = $this->versionPdfPath($bondRequest, $versionNumber);
        $docxPath = $this->versionDocxPath($bondRequest, $versionNumber);
        $this->writeFile($pdfPath, '%PDF-1.4 test');
        $this->writeFile($docxPath, 'docx test');

        return CertificateVersion::create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => $versionNumber,
            'certificate_type' => $bondRequest->certificate_type,
            'docx_path' => $docxPath,
            'pdf_path' => $pdfPath,
            'generated_by' => $generatedBy->id,
            'generated_at' => now(),
            'is_current' => $isCurrent,
        ]);
    }

    private function versionPdfPath(BondRequest $bondRequest, int $versionNumber): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        return "private/certificates/{$year}/{$month}/request_{$bondRequest->id}_v{$versionNumber}.pdf";
    }

    private function versionDocxPath(BondRequest $bondRequest, int $versionNumber): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        return "private/generated-docx/{$year}/{$month}/request_{$bondRequest->id}_v{$versionNumber}.docx";
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $absolutePath = storage_path('app/'.$relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);
    }

    private function validGeneratePayload(BondRequest $bondRequest): array
    {
        $signatory = $bondRequest->signatory ?? Signatory::factory()->create(['is_active' => true]);
        $notary = $bondRequest->notary ?? Notary::factory()->create(['is_active' => true]);

        return [
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'doc_no' => $bondRequest->doc_no ?? '1',
            'page_no' => $bondRequest->page_no ?? '1',
            'book_no' => $bondRequest->book_no ?? 'I',
            'series_year' => $bondRequest->series_year ?? '2026',
        ];
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

    private function approverUser(): User
    {
        $role = Role::where('slug', RoleSlug::Approver->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
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
        $branch = $this->makeBranch('MKT');
        $creator = $ownedBy ?? User::factory()->create(['branch_id' => $branch->id]);
        if ($ownedBy !== null && $ownedBy->branch_id === null) {
            $creator->update(['branch_id' => $branch->id]);
        }
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);

        return BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'created_by' => $creator->id,
                'status' => BondRequestStatus::Approved->value,
                'tin' => '123-456-789-0000',
            ]);
    }
}
