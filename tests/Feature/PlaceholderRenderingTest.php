<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\PartyType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use App\Services\AmountToWordsService;
use App\Services\CertificateGenerationService;
use App\Services\PlaceholderRenderer;
use App\Services\TemplateDataBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceholderRenderingTest extends TestCase
{
    use RefreshDatabase;

    private TemplateDataBuilder $builder;

    private PlaceholderRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->builder = new TemplateDataBuilder(new AmountToWordsService);
        $this->renderer = new PlaceholderRenderer;
    }

    public function test_government_party_type_builds_jurat_template_with_nested_placeholders(): void
    {
        $bondRequest = $this->bondRequest([
            'party_type' => PartyType::Government,
            'request_date' => '2026-06-12',
        ]);

        $raw = $this->builder->build($bondRequest)['text'];
        $rendered = $this->renderer->render($raw);

        $this->assertStringContainsString('SUBSCRIBED AND SWORN to before me this', $rendered['Jurat']);
        $this->assertStringNotContainsString('[[', $rendered['Jurat']);
    }

    public function test_private_party_type_builds_blank_jurat(): void
    {
        $bondRequest = $this->bondRequest([
            'party_type' => PartyType::Private,
        ]);

        $raw = $this->builder->build($bondRequest)['text'];

        $this->assertSame('', $raw['Jurat']);
    }

    public function test_include_endorsement_number_builds_nested_endorsement_template(): void
    {
        $bondRequest = $this->bondRequest([
            'include_endorsement_number' => true,
            'endorsement_number' => '2026-001234',
        ]);

        $raw = $this->builder->build($bondRequest)['text'];
        $rendered = $this->renderer->render($raw);

        $this->assertSame('W/ENDT.NO. 2026-001234', $rendered['Endorsement']);
        $this->assertSame('2026-001234', $rendered['Endorsement No.']);
    }

    public function test_exclude_endorsement_number_builds_blank_endorsement_values(): void
    {
        $bondRequest = $this->bondRequest([
            'include_endorsement_number' => false,
            'endorsement_number' => null,
        ]);

        $raw = $this->builder->build($bondRequest)['text'];

        $this->assertSame('', $raw['Endorsement']);
        $this->assertSame('', $raw['Endorsement No.']);
    }

    public function test_certificate_generation_is_blocked_when_endorsement_number_is_missing(): void
    {
        $approver = $this->approverUser();
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::CarCertificate,
            'include_endorsement_number' => true,
            'endorsement_number' => null,
        ]);

        $response = $this->actingAs($approver)->post(
            route('bond-requests.generate-certificate', $bondRequest),
            [],
        );

        $response->assertSessionHasErrors('endorsement_number');
    }

    public function test_certificate_generation_service_rejects_missing_endorsement_number(): void
    {
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::CarCertificate,
            'include_endorsement_number' => true,
            'endorsement_number' => '',
        ]);

        $service = app(CertificateGenerationService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endorsement number is required');

        $service->generate($bondRequest);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function bondRequest(array $overrides = []): BondRequest
    {
        $signatory = Signatory::factory()->create([
            'position' => 'Manager',
            'tin' => '123-456-789-0000',
            'is_active' => true,
        ]);
        $notary = Notary::factory()->create(['is_active' => true]);

        $bondRequest = BondRequest::factory()->approved()->create(array_merge([
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => PartyType::Private->value,
            'include_endorsement_number' => false,
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'request_date' => '2026-06-07',
            'date_issued' => '2026-06-07',
        ], $overrides));

        return $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch', 'bondTypeMaster']);
    }

    private function approverUser(): User
    {
        $role = Role::where('slug', RoleSlug::Approver->value)->firstOrFail();
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'branch_code' => 'MNL',
            'branch_city' => 'Makati City',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
