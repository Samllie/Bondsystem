<?php

namespace Tests\Feature;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Services\KycObligeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BondRequestFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_requester_can_create_bond_request_with_form_fields(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                    'business_address' => '123 Rizal Avenue',
                    'business_ctm' => 'Manila',
                    'business_province' => 'Metro Manila',
                ]);
        });

        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create([
            'name' => 'Retention Money Bond',
            'code' => 'G(42)',
            'bond_serial' => '0008384',
        ]);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'address_1' => '123 Rizal Avenue',
            'address_2' => 'Manila',
            'address_3' => 'Metro Manila',
            'amount' => 1500.75,
            'project_name' => 'Highway Project',
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'attention' => 'Ms. Jane Doe',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('bond_number', 'G(42)')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNotNull($bondRequest->supporting_document_path);
        Storage::disk('public')->assertExists($bondRequest->supporting_document_path);
        $bondRequest->load(['bondTypeMaster', 'creator.branch']);
        $this->assertSame('2026-05-01', $bondRequest->inception_date->toDateString());
        $this->assertSame('Retention Money Bond NO. G(42)-MKT-0008384', $bondRequest->bond_label);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'bond_type_id' => $bondType->id,
            'bond_type' => 'Retention Money Bond',
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'address_1' => '123 Rizal Avenue',
            'address_2' => 'Manila',
            'address_3' => 'Metro Manila',
            'project_name' => 'Highway Project',
            'attention' => 'Ms. Jane Doe',
            'certificate_type' => CertificateType::BondCertificate->value,
            'signatory_id' => null,
            'notary_id' => null,
            'status' => 'pending',
            'created_by' => $requester->id,
        ]);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'amount_in_words' => 'One Thousand Five Hundred Pesos and Seventy Five Centavos Only',
        ]);
    }

    public function test_requester_can_create_bond_request_without_attention(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                    'business_address' => '123 Rizal Avenue',
                    'business_ctm' => 'Manila',
                    'business_province' => 'Metro Manila',
                ]);
        });

        $requester = $this->requesterUser();
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create([
            'name' => 'Performance Bond',
            'code' => '7654321',
        ]);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::CarCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => '7654321',
            'attention' => null,
            'certificate_type' => CertificateType::CarCertificate->value,
        ]);
    }

    public function test_requester_cannot_create_bond_request_without_branch_code(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                ]);
        });

        $requester = User::factory()->create([
            'role_id' => Role::where('slug', RoleSlug::Requester->value)->firstOrFail()->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create(['code' => '1234567']);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('bond_type_id');
    }

    public function test_bond_request_uses_registered_bond_number_format(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                ]);
        });

        $requester = $this->requesterUser();
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create([
            'name' => 'Performance Bond',
            'code' => '0123456',
            'bond_serial' => '0000009',
        ]);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => '0123456',
            'bond_type_id' => $bondType->id,
        ]);
    }

    public function test_expiry_date_accepts_text_format(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                ]);
        });

        $requester = $this->requesterUser();
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => 'May 24, 2027',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertSame('May 24, 2027', $bondRequest->expiry_date);
    }

    public function test_expiry_date_accepts_validity_statement(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                ]);
        });

        $requester = $this->requesterUser();
        $statement = 'until fully recouped and liquidated is valid';

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => BondTypeMaster::factory()->create()->id,
            'principal_id' => Principal::factory()->create()->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => $statement,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'expiry_date' => $statement,
            'created_by' => $requester->id,
        ]);
    }

    public function test_requester_cannot_submit_bond_request_with_zero_obligee_id(): void
    {
        $requester = $this->requesterUser();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => BondTypeMaster::factory()->create()->id,
            'principal_id' => Principal::factory()->create()->id,
            'obligee_id' => 0,
            'obligee_name' => 'Typed But Not Selected',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('obligee_id');
        $this->assertDatabaseCount('bond_requests', 0);
    }

    public function test_approver_can_approve_pending_bond_request_with_certificate_details(): void
    {
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create(['name' => 'Jane Signer', 'position' => 'President']);
        $notary = Notary::factory()->create(['name' => 'Atty. Juan Notary']);
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'inception_date' => '2026-05-01',
        ]);

        $response = $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'doc_no' => 'DOC-1',
            'page_no' => '10',
            'book_no' => 'V',
            'series_year' => '2026',
        ]);

        $response->assertRedirect();

        $bondRequest->refresh();

        $this->assertSame(BondRequestStatus::Approved, $bondRequest->status);
        $this->assertSame($signatory->id, $bondRequest->signatory_id);
        $this->assertSame('President', $bondRequest->signatory_position);
        $this->assertSame($notary->id, $bondRequest->notary_id);
        $this->assertSame('DOC-1', $bondRequest->doc_no);
        $this->assertSame('V', $bondRequest->book_no);
    }

    private function requesterUser(string $branchCode = 'CEB'): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::query()->create([
            'name' => "{$branchCode} Branch",
            'branch_code' => $branchCode,
            'address' => 'Branch City',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branchCode,
            'branch_city' => 'Branch City',
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
}
