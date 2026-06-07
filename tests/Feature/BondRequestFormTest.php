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

    private const VALID_TIN = '123-456-789-0000';

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
            'tin' => self::VALID_TIN,
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
        $this->assertSame(self::VALID_TIN, $bondRequest->tin);

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
            'signatory_id' => null,
            'notary_id' => null,
            'tin' => self::VALID_TIN,
            'status' => 'pending',
            'created_by' => $requester->id,
        ]);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'G(42)',
            'amount_in_words' => 'One Thousand Five Hundred Pesos and Seventy Five Centavos Only',
        ]);

        $requester->refresh();
        $this->assertEquals(10000, (float) $requester->balance);
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
            'tin' => self::VALID_TIN,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('bond_requests', 1);
        $this->assertEquals(100, (float) $requester->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_approval_fails_when_requester_balance_is_insufficient_for_notary_fee(): void
    {
        $requester = $this->requesterUser('MKT', balance: 100, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create();
        $notary = Notary::factory()->create();
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
            'tin' => self::VALID_TIN,
        ]);

        $response = $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'doc_no' => 'DOC-1',
            'page_no' => '10',
            'book_no' => 'V',
            'series_year' => '2026',
        ]);

        $response->assertSessionHasErrors('signatory_id');
        $this->assertSame(BondRequestStatus::Pending, $bondRequest->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertEquals(100, (float) $requester->fresh()->balance);
    }

    public function test_approval_fails_when_requester_branch_has_no_notary_price(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 0);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create();
        $notary = Notary::factory()->create();
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'created_by' => $requester->id,
            'tin' => self::VALID_TIN,
        ]);

        $response = $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'doc_no' => 'DOC-1',
            'page_no' => '10',
            'book_no' => 'V',
            'series_year' => '2026',
        ]);

        $response->assertSessionHasErrors('signatory_id');
        $this->assertSame(BondRequestStatus::Pending, $bondRequest->fresh()->status);
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
            'tin' => '123-456-789-0000',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
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
            'bond_number' => 'CAR-CEB-0072056',
            'car' => 'CAR-CEB-0072056',
            'bond_type' => 'CAR',
            'attention' => null,
            'certificate_type' => CertificateType::CarCertificate->value,
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
            'tin' => '111-222-333-0000',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
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

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertSame('CAR-MKT-0072056', $bondRequest->car);
        $this->assertNull($bondRequest->bond_type_id);
        $this->assertSame('CAR-MKT-0072056', $bondRequest->bond_label);
        $this->assertSame('Maria Santos', $bondRequest->authorized_representative);
        $this->assertSame('111-222-333-0000', $bondRequest->tin);
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
            'tin' => '111-222-333-0000',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'certificate_type' => CertificateType::CarCertificate->value,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionDoesntHaveErrors('inception_date');
        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNull($bondRequest->inception_date);
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
            'tin' => '111-222-333-0000',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'certificate_type' => CertificateType::BondCertificate->value,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('inception_date');
    }

    public function test_requester_cannot_create_car_certificate_without_valid_tin(): void
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
            'tin' => '111-222-333',
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::CarCertificate->value,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('tin');
        $this->assertDatabaseCount('bond_requests', 0);
    }

    public function test_requester_cannot_create_bond_certificate_without_valid_tin(): void
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
            'tin' => '111-222-333',
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('tin');
        $this->assertDatabaseCount('bond_requests', 0);
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
            'tin' => self::VALID_TIN,
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
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'tin' => self::VALID_TIN,
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
            'principal_name' => $principal->company_name,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'tin' => self::VALID_TIN,
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
            'tin' => self::VALID_TIN,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
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
            'tin' => self::VALID_TIN,
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
            'tin' => self::VALID_TIN,
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertRedirect();

        $bondRequest = BondRequest::query()->where('created_by', $requester->id)->latest('id')->first();
        $this->assertNotNull($bondRequest);
        $this->assertNull($bondRequest->supporting_document_path);
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
            'tin' => self::VALID_TIN,
            'supporting_document' => UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
            'expiry_date' => '2027-05-24',
        ]);

        $response->assertSessionHasErrors('obligee_id');
        $this->assertDatabaseCount('bond_requests', 0);
    }

    public function test_approver_can_approve_pending_bond_request_with_certificate_details(): void
    {
        $requester = $this->requesterUser('MKT', balance: 10000, notaryPrice: 500);
        $approver = $this->approverUser();
        $signatory = Signatory::factory()->create(['name' => 'Jane Signer', 'position' => 'President']);
        $notary = Notary::factory()->create(['name' => 'Atty. Juan Notary']);
        $bondRequest = BondRequest::factory()->pending()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'inception_date' => '2026-05-01',
            'created_by' => $requester->id,
            'tin' => self::VALID_TIN,
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

        $requester->refresh();
        $this->assertEquals(9500, (float) $requester->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $requester->id,
            'type' => 'debit',
            'amount' => 500,
            'balance_before' => 10000,
            'balance_after' => 9500,
            'subject_type' => BondRequest::class,
            'subject_id' => $bondRequest->id,
        ]);
    }

    private function requesterUser(string $branchCode = 'CEB', float $balance = 10000, float $notaryPrice = 500): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::query()->create([
            'name' => "{$branchCode} Branch",
            'branch_code' => $branchCode,
            'address' => 'Branch City',
            'notary_price' => $notaryPrice,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branchCode,
            'branch_city' => 'Branch City',
            'balance' => $balance,
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
