<?php

namespace Tests\Feature;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\PartyType;
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

    private const SIGNATORY_TIN = '123-456-789-0000';

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
            'principal_name' => $principal->company_name,
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
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('bond_number', 'G(42)')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNotNull($bondRequest->supporting_document_paths);
        $this->assertCount(1, $bondRequest->supporting_document_paths);
        Storage::disk('public')->assertExists($bondRequest->supporting_document_paths[0]);
        $bondRequest->load(['bondTypeMaster', 'creator.branch']);
        $this->assertSame('2026-05-01', $bondRequest->inception_date->toDateString());
        $this->assertSame('Retention Money Bond NO. G(42)-MKT-0008384', $bondRequest->bond_label);
        $this->assertNull($bondRequest->tin);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'bond_type_id' => $bondType->id,
            'bond_type' => 'Retention Money Bond',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'address_1' => '123 Rizal Avenue',
            'address_2' => 'Manila',
            'address_3' => 'Metro Manila',
            'project_name' => 'Highway Project',
            'attention' => 'Ms. Jane Doe',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'signatory_id' => null,
            'notary_id' => null,
            'tin' => null,
            'status' => 'pending',
            'created_by' => $requester->id,
        ]);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'amount_in_words' => 'One Thousand Five Hundred Pesos and Seventy Five Centavos Only',
        ]);

        $requester->refresh();
        $this->assertEquals(10000, (float) $requester->branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_bond_request_creation_succeeds_even_when_balance_is_insufficient_for_notary_fee(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')->andReturn([
                'id' => 42,
                'company_name' => 'Acme Obligee Corp',
                'label' => 'Acme Obligee Corp',
            ]);
        });

        $requester = $this->requesterUser('MKT', balance: 100, notaryPrice: 500);
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create(['code' => 'G(42)', 'bond_serial' => '0008384']);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('bond_requests', 1);
        $this->assertEquals(100, (float) $requester->branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_approval_with_notary_does_not_charge_notary_fee_until_generation(): void
    {
        $requester = $this->requesterUser('MKT', balance: 100, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create();
        $notary = Notary::factory()->create();
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
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
        $this->assertSame(BondRequestStatus::Approved, $bondRequest->fresh()->status);
        $this->assertEquals(100, (float) $requester->branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_approval_succeeds_when_branch_has_no_notary_price_configured(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 0);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create();
        $notary = Notary::factory()->create();
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
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
        $this->assertSame(BondRequestStatus::Approved, $bondRequest->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
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

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'car' => 'CAR-CEB-0072056',
            'authorized_representative' => 'Juan Dela Cruz',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::CarCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'CAR-CEB-0072056',
            'car' => 'CAR-CEB-0072056',
            'bond_type' => 'CAR',
            'attention' => null,
            'certificate_type' => CertificateType::CarCertificate->value,
            'party_type' => 'private',
        ]);
    }

    public function test_requester_can_create_car_certificate_bond_request(): void
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

        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'car' => 'CAR-MKT-0072056',
            'authorized_representative' => 'Maria Santos',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::CarCertificate->value,
            'party_type' => 'government',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertSame('CAR-MKT-0072056', $bondRequest->car);
        $this->assertNull($bondRequest->bond_type_id);
        $this->assertSame('CAR-MKT-0072056', $bondRequest->bond_label);
        $this->assertSame('Maria Santos', $bondRequest->authorized_representative);
        $this->assertNull($bondRequest->tin);
        $this->assertSame(PartyType::Government, $bondRequest->party_type);
    }

    public function test_car_certificate_does_not_require_inception_date(): void
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

        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'car' => 'CAR-MKT-0072056',
            'authorized_representative' => 'Maria Santos',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'certificate_type' => CertificateType::CarCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionDoesntHaveErrors('inception_date');
        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNull($bondRequest->inception_date);
        $this->assertSame(PartyType::Private, $bondRequest->party_type);
    }

    public function test_bond_certificate_still_requires_inception_date(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')->andReturn([
                'id' => 42,
                'company_name' => 'Acme Obligee Corp',
                'label' => 'Acme Obligee Corp',
            ]);
        });

        $requester = $this->requesterUser('MKT');
        $bondType = BondTypeMaster::factory()->create();
        $principal = Principal::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('inception_date');
    }

    public function test_requester_must_provide_endorsement_number_when_endorsement_is_enabled(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')->andReturn([
                'id' => 42,
                'company_name' => 'Acme Obligee Corp',
                'label' => 'Acme Obligee Corp',
            ]);
        });

        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'include_endorsement_number' => true,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('endorsement_number');
        $this->assertDatabaseCount('bond_requests', 0);
    }

    public function test_requester_can_store_endorsement_number_on_bond_request(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')->andReturn([
                'id' => 42,
                'company_name' => 'Acme Obligee Corp',
                'label' => 'Acme Obligee Corp',
            ]);
        });

        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create(['code' => 'G(42)', 'bond_serial' => '0008384']);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'include_endorsement_number' => true,
            'endorsement_number' => 'END-2026-001',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'created_by' => $requester->id,
            'include_endorsement_number' => true,
            'endorsement_number' => 'END-2026-001',
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
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
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
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
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
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
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
        $principal = Principal::factory()->create();
        $statement = 'until fully recouped and liquidated is valid';

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => BondTypeMaster::factory()->create()->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            'expiry_date' => $statement,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'expiry_date' => $statement,
            'created_by' => $requester->id,
        ]);
    }

    public function test_requester_can_create_bond_request_with_typed_obligee_and_principal(): void
    {
        $requester = $this->requesterUser('MKT');
        $bondType = BondTypeMaster::factory()->create([
            'name' => 'Retention Money Bond',
            'code' => 'G(42)',
            'bond_serial' => '0008384',
        ]);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_name' => 'Custom Principal Corp',
            'obligee_name' => 'Custom Obligee Corp',
            'address_1' => '456 Custom Street',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'principal_id' => null,
            'principal_name' => 'Custom Principal Corp',
            'obligee_id' => null,
            'obligee_name' => 'Custom Obligee Corp',
            'created_by' => $requester->id,
        ]);
    }

    public function test_requester_can_create_bond_request_without_supporting_document(): void
    {
        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_name' => 'Typed Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNull($bondRequest->supporting_document_paths);
    }

    public function test_approver_can_approve_without_certificate_details(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 500);
        $approver = $this->approverUser();
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::CarCertificate,
            'car' => 'CAR-MKT-0072056',
            'bond_number' => 'CAR-MKT-0072056',
            'created_by' => $requester->id,
        ]);

        $response = $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'series_year' => '2026',
        ]);

        $response->assertRedirect();

        $bondRequest->refresh();

        $this->assertSame(BondRequestStatus::Approved, $bondRequest->status);
        $this->assertNull($bondRequest->signatory_id);
        $this->assertNull($bondRequest->doc_no);
    }

    public function test_requester_cannot_submit_bond_request_with_zero_obligee_id(): void
    {
        $requester = $this->requesterUser();
        $principal = Principal::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => BondTypeMaster::factory()->create()->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 0,
            'obligee_name' => 'Typed But Not Selected',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('obligee_id');
        $this->assertDatabaseCount('bond_requests', 0);
    }

    public function test_approver_can_approve_pending_bond_request_with_certificate_details(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create([
            'name' => 'Jane Signer',
            'position' => 'President',
            'tin' => self::SIGNATORY_TIN,
        ]);
        $notary = Notary::factory()->create(['name' => 'Atty. Juan Notary']);
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'inception_date' => '2026-05-01',
            'created_by' => $requester->id,
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
        $this->assertSame(self::SIGNATORY_TIN, $bondRequest->tin);

        $requester->refresh();
        $this->assertEquals(10000, (float) $requester->branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_approver_can_approve_without_notary_and_no_fee_is_charged(): void
    {
        $requester = $this->requesterUser('MKT', balance: 100, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create(['tin' => self::SIGNATORY_TIN]);
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
        ]);

        $response = $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'include_signatory_signature' => true,
            'doc_no' => 'DOC-1',
            'series_year' => '2026',
        ]);

        $response->assertRedirect();

        $bondRequest->refresh();

        $this->assertSame(BondRequestStatus::Approved, $bondRequest->status);
        $this->assertNull($bondRequest->notary_id);
        $this->assertTrue($bondRequest->include_signatory_signature);
        $this->assertEquals(100, (float) $requester->branch->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_approval_persists_include_signatory_signature_only_when_checked(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create(['tin' => self::SIGNATORY_TIN]);
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'include_signatory_signature' => false,
        ])->assertRedirect();

        $this->assertFalse($bondRequest->fresh()->include_signatory_signature);
    }

    public function test_requester_can_upload_up_to_five_supporting_documents(): void
    {
        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $files = [];
        for ($index = 1; $index <= 5; $index++) {
            $files[] = UploadedFile::fake()->create("support-{$index}.pdf", 100, 'application/pdf');
        }

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_name' => 'Typed Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => $files,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertCount(5, $bondRequest->supporting_document_paths);

        foreach ($bondRequest->supporting_document_paths as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringStartsWith('supporting-documents/', $path);
        }
    }

    public function test_requester_cannot_upload_more_than_five_supporting_documents(): void
    {
        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $files = [];
        for ($index = 1; $index <= 6; $index++) {
            $files[] = UploadedFile::fake()->create("support-{$index}.pdf", 100, 'application/pdf');
        }

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_name' => 'Typed Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => $files,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('supporting_documents');
    }

    public function test_supporting_document_larger_than_15mb_is_rejected(): void
    {
        $requester = $this->requesterUser('MKT');
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create();

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_name' => 'Typed Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'supporting_documents' => [
                UploadedFile::fake()->create('large.pdf', 15361, 'application/pdf'),
            ],
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('supporting_documents.0');
    }

    private function requesterUser(string $branchCode = 'CEB', float $balance = 10000, float $notaryPrice = 500): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::query()->create([
            'name' => "{$branchCode} Branch",
            'branch_code' => $branchCode,
            'address' => 'Branch City',
            'notary_price' => $notaryPrice,
            'balance' => $balance,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branchCode,
            'branch_city' => 'Branch City',
            'balance' => 0,
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
