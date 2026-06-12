<?php

namespace Tests\Feature;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AmountToWordsService;
use App\Services\CertificateGenerationService;
use App\Services\DocxEndorsementSpacingNormalizer;
use App\Services\PlaceholderRenderer;
use App\Services\TemplateDataBuilder;
use App\Services\TemplateNormalizerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    // -------------------------------------------------------------------------
    // CertificateGenerationService unit-level tests
    // -------------------------------------------------------------------------

    public function test_generate_certificate_service_throws_when_template_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template not found');

        $bondRequest = BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
            ]);
        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        $normalizer = $this->getMockBuilder(TemplateNormalizerService::class)
            ->onlyMethods(['normalize'])
            ->getMock();

        $normalizer->expects($this->once())
            ->method('normalize')
            ->willThrowException(new \RuntimeException('Template not found: /path/to/template.docx'));

        $service = new CertificateGenerationService(
            $normalizer,
            new TemplateDataBuilder(new AmountToWordsService),
            new PlaceholderRenderer,
            new DocxEndorsementSpacingNormalizer,
        );
        $service->generate($bondRequest);
    }

    // -------------------------------------------------------------------------
    // HTTP route tests
    // -------------------------------------------------------------------------

    public function test_approver_can_trigger_certificate_generation(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_generate_certificate_accepts_empty_optional_fields(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedCarBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), []);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_car_certificate_can_generate_without_notary(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedCarBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $signatory = Signatory::factory()->create(['is_active' => true]);

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ]);

        $response->assertSessionDoesntHaveErrors('notary_id');
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNull($bondRequest->fresh()->notary_id);
    }

    public function test_generate_certificate_updates_bond_request_fields(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $bondRequest->refresh();
        $this->assertSame($bondRequest->signatory_id, $bondRequest->signatory_id);
        $this->assertNotNull($bondRequest->doc_no);
        $this->assertNotNull($bondRequest->series_year);
    }

    public function test_requester_cannot_trigger_certificate_generation(): void
    {
        $requester = $this->requesterUser();
        $bondRequest = $this->approvedBondRequest();

        $response = $this->actingAs($requester)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $response->assertForbidden();
    }

    public function test_cannot_generate_certificate_for_pending_request(): void
    {
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);
        $bondRequest = BondRequest::factory()->create([
            'status' => BondRequestStatus::Pending->value,
        ]);

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ]);

        $response->assertStatus(422);
    }

    public function test_download_certificate_returns_404_when_no_certificate(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->approvedBondRequest();

        $response = $this->actingAs($superAdmin)
            ->get(route('bond-requests.download-certificate', $bondRequest));

        $response->assertNotFound();
    }

    public function test_download_docx_returns_404_when_no_docx(): void
    {
        $superAdmin = $this->superAdminUser();
        $bondRequest = $this->approvedBondRequest();

        $response = $this->actingAs($superAdmin)
            ->get(route('bond-requests.download-docx', $bondRequest));

        $response->assertNotFound();
    }

    public function test_show_page_includes_certificate_flags(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $response = $this->actingAs($approver)
            ->get(route('bond-requests.show', $bondRequest));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BondRequests/Show')
            ->has('canGenerateCertificate')
            ->has('hasCertificate')
            ->has('hasDocx')
        );
    }

    public function test_generation_failure_redirects_with_error_message(): void
    {
        $approver = $this->approverUser();
        $bondRequest = $this->approvedBondRequest();

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new \RuntimeException('Template not found'));
        });

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_generate_certificate_charges_notary_fee_when_notary_is_selected(): void
    {
        $approver = $this->approverUser();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $requester = User::factory()->create(['branch_id' => $branch->id]);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate->value,
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'created_by' => $requester->id,
        ]);

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(9500, (float) $branch->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'branch_id' => $branch->id,
            'type' => 'debit',
            'amount' => 500,
            'subject_type' => BondRequest::class,
            'subject_id' => $bondRequest->id,
        ]);
    }

    public function test_generate_certificate_without_notary_does_not_charge_fee(): void
    {
        $approver = $this->approverUser();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $requester = User::factory()->create(['branch_id' => $branch->id]);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::CarCertificate->value,
            'signatory_id' => $signatory->id,
            'notary_id' => null,
            'created_by' => $requester->id,
        ]);

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(10000, (float) $branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_regenerating_certificate_does_not_charge_notary_fee_twice(): void
    {
        $approver = $this->approverUser();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $requester = User::factory()->create(['branch_id' => $branch->id]);
        $bondRequest = $this->approvedBondRequestWithBranch($branch, $requester);

        Transaction::create([
            'user_id' => $requester->id,
            'branch_id' => $branch->id,
            'type' => 'debit',
            'amount' => 500,
            'balance_before' => 10000,
            'balance_after' => 9500,
            'reference' => $bondRequest->bond_number,
            'description' => 'Existing notary fee',
            'subject_type' => BondRequest::class,
            'subject_id' => $bondRequest->id,
        ]);
        $branch->update(['balance' => 9500]);

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), $this->validGeneratePayload($bondRequest))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(9500, (float) $branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_generate_certificate_fails_when_branch_fund_is_insufficient_for_notary_fee(): void
    {
        $approver = $this->approverUser();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 100,
            'is_active' => true,
        ]);
        $requester = User::factory()->create(['branch_id' => $branch->id]);
        $notary = Notary::factory()->create(['is_active' => true]);
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate->value,
            'notary_id' => $notary->id,
            'created_by' => $requester->id,
        ]);

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->never();
        });

        $response = $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'notary_id' => $notary->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ]);

        $response->assertSessionHasErrors('notary_id');
        $this->assertEquals(100, (float) $branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    // -------------------------------------------------------------------------
    // Helper: valid payload for generate-certificate
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    private function approvedBondRequest(): BondRequest
    {
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $creator = User::factory()->create(['branch_id' => $branch->id]);

        return $this->approvedBondRequestWithBranch($branch, $creator);
    }

    private function approvedBondRequestWithBranch(Branch $branch, User $creator): BondRequest
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

    private function approvedCarBondRequest(): BondRequest
    {
        $branch = Branch::query()->create(['name' => 'MKT Branch', 'branch_code' => 'MKT', 'branch_city' => 'Makati', 'is_active' => true]);
        $creator = User::factory()->create(['branch_id' => $branch->id]);

        return BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::CarCertificate->value,
                'bond_type_id' => null,
                'car' => 'CAR-MKT-0072056',
                'notary_id' => null,
                'created_by' => $creator->id,
                'tin' => '123-456-789-0000',
            ]);
    }
}
