<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Principal;
use App\Models\User;
use App\Services\AmountToWordsService;
use App\Services\TemplateDataBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDataBuilderTest extends TestCase
{
    use RefreshDatabase;

    private TemplateDataBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        $this->builder = new TemplateDataBuilder(new AmountToWordsService);
    }

    // -------------------------------------------------------------------------
    // Certificate type guard
    // -------------------------------------------------------------------------

    public function test_throws_when_certificate_type_is_null(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no certificate type');

        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => null,
        ]);
        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        $this->builder->build($bondRequest);
    }

    // -------------------------------------------------------------------------
    // Bond Certificate placeholder mapping
    // -------------------------------------------------------------------------

    public function test_bond_certificate_text_keys_are_present(): void
    {
        $bondRequest = $this->bondRequest();

        $data = $this->builder->build($bondRequest);

        $expectedTextKeys = [
            'Date', 'Date issued', 'Expiry date', 'Obligee', 'Address line 1',
            'Address line 2', 'Address line 3', 'Project name', 'Amount', 'Amount in words',
            'Tin', 'Branch city', 'Signatory', 'Position', 'Doc. No.', 'Page No.', 'Book No.', 'Endorsement No.', 'Jurat', 'Endorsement',
            'Date in words', 'Date issued in words',
            'Bond', 'BOND', 'PRINCIPAL', 'Series year',
        ];

        foreach ($expectedTextKeys as $key) {
            $this->assertArrayHasKey($key, $data['text'], "Missing text placeholder: {$key}");
        }

        // Notary is an image placeholder when a seal exists, otherwise an empty text fallback.
        $this->assertTrue(
            isset($data['images']['Notary']) || isset($data['text']['Notary']),
            'Notary placeholder must appear in either images or text',
        );
    }

    public function test_bond_date_comes_from_request_date(): void
    {
        $bondRequest = $this->bondRequest(['request_date' => '2026-06-07']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('June 07, 2026', $data['text']['Date']);
    }

    public function test_bond_date_issued_comes_from_date_issued(): void
    {
        $bondRequest = $this->bondRequest(['date_issued' => '2026-03-15']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('March 15, 2026', $data['text']['Date issued']);
    }

    public function test_bond_date_in_words_uses_request_date(): void
    {
        $bondRequest = $this->bondRequest(['request_date' => '2026-06-07']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('7th day of June, 2026', $data['text']['Date in words']);
    }

    public function test_bond_date_issued_in_words_uses_date_issued(): void
    {
        $bondRequest = $this->bondRequest(['date_issued' => '2026-01-01']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('1st day of January, 2026', $data['text']['Date issued in words']);
    }

    public function test_bond_uses_amount_in_words_stored_value(): void
    {
        $bondRequest = $this->bondRequest([
            'amount' => 500000,
            'amount_in_words' => 'Five Hundred Thousand Pesos Only',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Five Hundred Thousand Pesos Only', $data['text']['Amount in words']);
    }

    public function test_bond_generates_amount_in_words_when_stored_value_is_empty(): void
    {
        $bondRequest = $this->bondRequest([
            'amount' => 1500000,
            'amount_in_words' => '',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertStringContainsString('Million', $data['text']['Amount in words']);
        $this->assertStringContainsString('Pesos Only', $data['text']['Amount in words']);
    }

    public function test_bond_amount_is_formatted_as_php_currency(): void
    {
        $bondRequest = $this->bondRequest(['amount' => 1500000]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('PHP 1,500,000.00', $data['text']['Amount']);
    }

    public function test_tin_comes_from_signatory(): void
    {
        $signatory = Signatory::factory()->create(['tin' => '555-666-777-0000']);
        $bondRequest = $this->bondRequest([
            'signatory_id' => $signatory->id,
            'tin' => '111-222-333-0000',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('555-666-777-0000', $data['text']['Tin']);
    }

    public function test_endorsement_number_is_mapped_to_template_placeholder(): void
    {
        $bondRequest = $this->bondRequest([
            'include_endorsement_number' => true,
            'endorsement_number' => 'END-2026-001',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('END-2026-001', $data['text']['Endorsement No.']);
    }

    public function test_bond_principal_is_uppercased(): void
    {
        $principal = Principal::factory()->create(['company_name' => 'Acme Corporation']);
        $bondRequest = $this->bondRequest(['principal_id' => $principal->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('ACME CORPORATION', $data['text']['PRINCIPAL']);
    }

    public function test_bond_label_is_set_for_bond_placeholder(): void
    {
        $bondRequest = $this->bondRequest();

        $data = $this->builder->build($bondRequest);

        $bondLabel = $bondRequest->bond_label;
        $this->assertSame($bondLabel, $data['text']['Bond']);
        $this->assertSame(strtoupper($bondLabel), $data['text']['BOND']);
    }

    public function test_bond_position_comes_from_signatory_position_not_bond_request(): void
    {
        $signatory = Signatory::factory()->create([
            'name' => 'Jane Doe',
            'position' => 'Senior Manager',
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest([
            'signatory_id' => $signatory->id,
            'signatory_position' => 'Old Position On Bond Request',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Senior Manager', $data['text']['Position']);
    }

    public function test_bond_branch_city_comes_from_creator_branch(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Makati Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati City',
            'is_active' => true,
        ]);
        $creator = User::factory()->create(['branch_id' => $branch->id]);
        $bondRequest = $this->bondRequest(['created_by' => $creator->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Makati City', $data['text']['Branch city']);
    }

    public function test_bond_optional_address_lines_are_empty_string_when_null(): void
    {
        $bondRequest = $this->bondRequest([
            'address_2' => null,
            'address_3' => null,
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('', $data['text']['Address line 2']);
        $this->assertSame('', $data['text']['Address line 3']);
    }

    public function test_bond_signature_goes_into_images_not_text_when_file_exists(): void
    {
        $signaturePath = 'signatures/test_sig.png';
        Storage::disk('public')->put($signaturePath, 'fake-png-content');

        $signatory = Signatory::factory()->create([
            'signature_path' => $signaturePath,
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest([
            'signatory_id' => $signatory->id,
            'include_signatory_signature' => true,
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertArrayHasKey('Signature', $data['images']);
        $this->assertArrayNotHasKey('Signature', array_filter($data['text'], fn ($v) => $v !== ''));
        $this->assertSame(120, $data['images']['Signature']['width']);
        $this->assertSame(60, $data['images']['Signature']['height']);
        $this->assertTrue($data['images']['Signature']['ratio']);
    }

    public function test_bond_signature_is_excluded_when_include_signatory_signature_is_false(): void
    {
        $signaturePath = 'signatures/test_sig_excluded.png';
        Storage::disk('public')->put($signaturePath, 'fake-png-content');

        $signatory = Signatory::factory()->create([
            'signature_path' => $signaturePath,
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest([
            'signatory_id' => $signatory->id,
            'include_signatory_signature' => false,
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertArrayNotHasKey('Signature', $data['images']);
        $this->assertArrayHasKey('Signature', $data['text']);
        $this->assertSame('', $data['text']['Signature']);
    }

    public function test_bond_signature_becomes_empty_text_when_no_signature_file(): void
    {
        $signatory = Signatory::factory()->create([
            'signature_path' => null,
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest(['signatory_id' => $signatory->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertArrayNotHasKey('Signature', $data['images']);
        $this->assertArrayHasKey('Signature', $data['text']);
        $this->assertSame('', $data['text']['Signature']);
    }

    public function test_bond_notary_seal_goes_into_images_not_text_when_file_exists(): void
    {
        $sealPath = 'notary-seals/test_seal.png';
        Storage::disk('public')->put($sealPath, 'fake-png-content');

        $notary = Notary::factory()->create([
            'signature_path' => $sealPath,
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest(['notary_id' => $notary->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertArrayHasKey('Notary', $data['images']);
        $this->assertArrayNotHasKey('Notary', $data['text']);
        $this->assertSame(100, $data['images']['Notary']['width']);
        $this->assertSame(100, $data['images']['Notary']['height']);
        $this->assertTrue($data['images']['Notary']['ratio']);
    }

    public function test_bond_notary_seal_becomes_empty_text_when_no_seal_file(): void
    {
        $notary = Notary::factory()->create([
            'signature_path' => null,
            'is_active' => true,
        ]);
        $bondRequest = $this->bondRequest(['notary_id' => $notary->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertArrayNotHasKey('Notary', $data['images']);
        $this->assertArrayHasKey('Notary', $data['text']);
        $this->assertSame('', $data['text']['Notary']);
    }

    public function test_bond_signatory_missing_produces_empty_strings_not_exception(): void
    {
        $bondRequest = $this->bondRequest(['signatory_id' => null]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('', $data['text']['Signatory']);
        $this->assertSame('', $data['text']['Position']);
    }

    // -------------------------------------------------------------------------
    // CAR Certificate placeholder mapping
    // -------------------------------------------------------------------------

    public function test_car_certificate_text_keys_are_present(): void
    {
        $bondRequest = $this->carBondRequest();

        $data = $this->builder->build($bondRequest);

        $expectedKeys = [
            'Date', 'Date issued', 'Expiry date', 'Obligee', 'Address line 1',
            'Address line 2', 'Address line 3', 'Project name', 'Amount', 'Amount in words',
            'Tin', 'Branch city', 'Signatory', 'Position', 'Doc. No.', 'Page No.', 'Book No.', 'Endorsement No.', 'Jurat', 'Endorsement',
            'Date in words', 'Date issued in words',
            'CAR', 'Branch', 'Year', 'Attention', 'Authorized Representative', 'Principal',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data['text'], "Missing CAR text placeholder: {$key}");
        }
    }

    public function test_car_principal_is_not_uppercased(): void
    {
        $principal = Principal::factory()->create(['company_name' => 'Acme Corporation']);
        $bondRequest = $this->carBondRequest(['principal_id' => $principal->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Acme Corporation', $data['text']['Principal']);
    }

    public function test_car_branch_comes_from_creator_branch_name(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Cebu Branch',
            'branch_code' => 'CEB',
            'branch_city' => 'Cebu City',
            'is_active' => true,
        ]);
        $creator = User::factory()->create(['branch_id' => $branch->id]);
        $bondRequest = $this->carBondRequest(['created_by' => $creator->id]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Cebu Branch', $data['text']['Branch']);
    }

    public function test_car_year_comes_from_series_year(): void
    {
        $bondRequest = $this->carBondRequest(['series_year' => '2026']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('2026', $data['text']['Year']);
    }

    public function test_car_certificate_includes_date_in_words_from_request_date(): void
    {
        $bondRequest = $this->carBondRequest(['request_date' => '2026-06-12']);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('12th day of June, 2026', $data['text']['Date in words']);
    }

    public function test_car_position_falls_back_to_signatory_position_on_bond_request(): void
    {
        $signatory = Signatory::factory()->create([
            'name' => 'Jane Doe',
            'position' => '',
            'is_active' => true,
        ]);
        $bondRequest = $this->carBondRequest([
            'signatory_id' => $signatory->id,
            'signatory_position' => 'Branch Manager',
        ]);

        $data = $this->builder->build($bondRequest);

        $this->assertSame('Jane Doe', $data['text']['Signatory']);
        $this->assertSame('Branch Manager', $data['text']['Position']);
    }

    public function test_car_has_no_bond_specific_placeholders(): void
    {
        $bondRequest = $this->carBondRequest();

        $data = $this->builder->build($bondRequest);

        $this->assertArrayNotHasKey('Bond', $data['text']);
        $this->assertArrayNotHasKey('BOND', $data['text']);
        $this->assertArrayNotHasKey('PRINCIPAL', $data['text']);
        $this->assertArrayNotHasKey('Notary', $data['text']);
        $this->assertArrayNotHasKey('Series year', $data['text']);
        $this->assertArrayNotHasKey('Signature', $data['images']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an approved Bond Certificate BondRequest with relations loaded.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function bondRequest(array $overrides = []): BondRequest
    {
        $signatory = Signatory::factory()->create(['position' => 'Manager', 'is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);

        $bondRequest = BondRequest::factory()->approved()->create(array_merge([
            'certificate_type' => CertificateType::BondCertificate->value,
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'tin' => '123-456-789-0000',
            'amount' => 500000,
            'amount_in_words' => 'Five Hundred Thousand Pesos Only',
            'request_date' => '2026-06-07',
            'date_issued' => '2026-06-07',
            'doc_no' => '1',
            'page_no' => '1',
            'book_no' => 'I',
            'series_year' => '2026',
        ], $overrides));

        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        return $bondRequest;
    }

    /**
     * Create an approved CAR Certificate BondRequest with relations loaded.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function carBondRequest(array $overrides = []): BondRequest
    {
        $signatory = Signatory::factory()->create(['position' => 'Manager', 'is_active' => true]);

        $bondRequest = BondRequest::factory()->carCertificate()->approved()->create(array_merge([
            'signatory_id' => $signatory->id,
            'request_date' => '2026-06-07',
            'date_issued' => '2026-06-07',
            'doc_no' => '1',
            'page_no' => '1',
            'book_no' => 'I',
            'series_year' => '2026',
        ], $overrides));

        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        return $bondRequest;
    }
}
