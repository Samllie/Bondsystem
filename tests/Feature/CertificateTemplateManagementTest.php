<?php

namespace Tests\Feature;

use App\Enums\CertificateTemplateType;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\CertificateTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\AmountToWordsService;
use App\Services\CertificateGenerationService;
use App\Services\TemplateDataBuilder;
use App\Services\TemplateNormalizerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_index_includes_currently_in_use_templates(): void
    {
        $admin = $this->superAdminUser();

        $this->actingAs($admin)
            ->get(route('certificate-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CertificateTemplates/Index')
                ->has('inUseTemplates', 2)
                ->where('inUseTemplates.0.template_type', 'bond')
                ->where('inUseTemplates.1.template_type', 'car')
                ->where('inUseTemplates.0.is_in_use', true)
                ->has('previousTemplates')
                ->has('archivedTemplates')
            );
    }

    public function test_deactivated_template_appears_in_previous_templates_section(): void
    {
        $admin = $this->superAdminUser();
        $first = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1, active: true);
        $second = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 2);

        $this->actingAs($admin)->patch(route('certificate-templates.activate', $second))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('certificate-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('previousTemplates', 1)
                ->where('previousTemplates.0.id', $first->id)
                ->where('previousTemplates.0.is_previous', true)
            );
    }

    public function test_super_admin_can_upload_bond_template(): void
    {
        $admin = $this->superAdminUser();
        $file = $this->validDocxUpload('bond-template.docx');

        $response = $this->actingAs($admin)->post(route('certificate-templates.store'), [
            'template_name' => 'Custom Bond Template',
            'template_type' => CertificateTemplateType::Bond->value,
            'template' => $file,
        ]);

        $response->assertRedirect(route('certificate-templates.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('certificate_templates', [
            'template_name' => 'Custom Bond Template',
            'template_type' => CertificateTemplateType::Bond->value,
            'version' => 1,
            'original_filename' => 'bond-template.docx',
            'uploaded_by' => $admin->id,
            'is_active' => false,
        ]);

        $template = CertificateTemplate::first();
        Storage::disk('local')->assertExists($template->file_path);
    }

    public function test_super_admin_can_upload_car_template(): void
    {
        $admin = $this->superAdminUser();

        $this->actingAs($admin)->post(route('certificate-templates.store'), [
            'template_name' => 'Custom CAR Template',
            'template_type' => CertificateTemplateType::Car->value,
            'template' => $this->validDocxUpload('car-template.docx'),
        ])->assertRedirect();

        $this->assertDatabaseHas('certificate_templates', [
            'template_name' => 'Custom CAR Template',
            'template_type' => CertificateTemplateType::Car->value,
            'version' => 1,
        ]);
    }

    public function test_requester_cannot_access_template_management(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester);

        $this->actingAs($requester)
            ->get(route('certificate-templates.index'))
            ->assertForbidden();
    }

    public function test_approver_cannot_access_template_management(): void
    {
        $approver = $this->userWithRole(RoleSlug::Approver);

        $this->actingAs($approver)
            ->get(route('certificate-templates.index'))
            ->assertForbidden();
    }

    public function test_only_one_active_bond_template_exists_after_activation(): void
    {
        $admin = $this->superAdminUser();
        $first = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1);
        $second = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 2);

        $this->actingAs($admin)->patch(route('certificate-templates.activate', $first))->assertRedirect();
        $this->actingAs($admin)->patch(route('certificate-templates.activate', $second))->assertRedirect();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(
            1,
            CertificateTemplate::query()
                ->where('template_type', CertificateTemplateType::Bond)
                ->where('is_active', true)
                ->count(),
        );
    }

    public function test_only_one_active_car_template_exists_after_activation(): void
    {
        $admin = $this->superAdminUser();
        $first = $this->createStoredTemplate($admin, CertificateTemplateType::Car, 1);
        $second = $this->createStoredTemplate($admin, CertificateTemplateType::Car, 2);

        $this->actingAs($admin)->patch(route('certificate-templates.activate', $first))->assertRedirect();
        $this->actingAs($admin)->patch(route('certificate-templates.activate', $second))->assertRedirect();

        $this->assertSame(
            1,
            CertificateTemplate::query()
                ->where('template_type', CertificateTemplateType::Car)
                ->where('is_active', true)
                ->count(),
        );
    }

    public function test_activating_template_deactivates_previous_active_template(): void
    {
        $admin = $this->superAdminUser();
        $previous = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1, active: true);
        $next = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 2);

        $this->actingAs($admin)->patch(route('certificate-templates.activate', $next))->assertRedirect();

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertTrue($next->fresh()->is_active);
    }

    public function test_archived_template_cannot_be_activated(): void
    {
        $admin = $this->superAdminUser();
        $template = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1);
        $template->update(['archived_at' => now(), 'is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('certificate-templates.activate', $template))
            ->assertStatus(422);

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_template_download_requires_authorization(): void
    {
        $admin = $this->superAdminUser();
        $requester = $this->userWithRole(RoleSlug::Requester);
        $template = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1);

        $this->actingAs($requester)
            ->get(route('certificate-templates.download', $template))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('certificate-templates.download', $template))
            ->assertOk();
    }

    public function test_certificate_generation_service_uses_active_template_when_available(): void
    {
        $admin = $this->superAdminUser();
        $storedPath = 'certificate-templates/bond_v1_active.docx';
        Storage::disk('local')->put($storedPath, $this->sampleDocxContents());

        CertificateTemplate::factory()->active()->create([
            'template_type' => CertificateTemplateType::Bond,
            'template_name' => 'Uploaded Bond',
            'version' => 1,
            'file_path' => $storedPath,
            'uploaded_by' => $admin->id,
        ]);

        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);

        $service = new CertificateGenerationService(
            new TemplateNormalizerService,
            new TemplateDataBuilder(new AmountToWordsService),
        );

        $method = new \ReflectionMethod($service, 'templatePath');
        $method->setAccessible(true);

        $path = $method->invoke($service, $bondRequest);

        $this->assertSame(Storage::disk('local')->path($storedPath), $path);
    }

    public function test_certificate_generation_service_falls_back_to_resources_templates(): void
    {
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);

        $service = new CertificateGenerationService(
            new TemplateNormalizerService,
            new TemplateDataBuilder(new AmountToWordsService),
        );

        $method = new \ReflectionMethod($service, 'templatePath');
        $method->setAccessible(true);

        $path = $method->invoke($service, $bondRequest);

        $this->assertSame(resource_path('templates/Sterling_Bond_Template.docx'), $path);
    }

    public function test_invalid_file_type_is_rejected_on_upload(): void
    {
        $admin = $this->superAdminUser();

        $response = $this->actingAs($admin)->post(route('certificate-templates.store'), [
            'template_name' => 'Bad Template',
            'template_type' => CertificateTemplateType::Bond->value,
            'template' => UploadedFile::fake()->create('template.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('template');
        $this->assertSame(0, CertificateTemplate::count());
    }

    public function test_file_size_validation_is_enforced_on_upload(): void
    {
        $admin = $this->superAdminUser();

        $response = $this->actingAs($admin)->post(route('certificate-templates.store'), [
            'template_name' => 'Large Template',
            'template_type' => CertificateTemplateType::Bond->value,
            'template' => UploadedFile::fake()->create(
                'large.docx',
                10241,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ]);

        $response->assertSessionHasErrors('template');
        $this->assertSame(0, CertificateTemplate::count());
    }

    public function test_archiving_template_marks_it_inactive(): void
    {
        $admin = $this->superAdminUser();
        $template = $this->createStoredTemplate($admin, CertificateTemplateType::Bond, 1, active: true);

        $this->actingAs($admin)->patch(route('certificate-templates.archive', $template))->assertRedirect();

        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertNotNull($template->archived_at);
    }

    private function superAdminUser(): User
    {
        return $this->userWithRole(RoleSlug::SuperAdmin);
    }

    private function userWithRole(RoleSlug $slug): User
    {
        $role = Role::where('slug', $slug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function validDocxUpload(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $filename,
            $this->sampleDocxContents(),
        );
    }

    private function sampleDocxContents(): string
    {
        $path = resource_path('templates/Sterling_Bond_Template.docx');

        if (file_exists($path)) {
            return (string) file_get_contents($path);
        }

        return 'PK'.str_repeat("\0", 100);
    }

    private function createStoredTemplate(
        User $admin,
        CertificateTemplateType $type,
        int $version,
        bool $active = false,
    ): CertificateTemplate {
        $storedPath = "certificate-templates/{$type->value}_v{$version}_test.docx";
        Storage::disk('local')->put($storedPath, $this->sampleDocxContents());

        return CertificateTemplate::factory()->create([
            'template_type' => $type,
            'template_name' => "{$type->label()} Template {$version}",
            'version' => $version,
            'file_path' => $storedPath,
            'original_filename' => "{$type->value}-{$version}.docx",
            'file_size' => Storage::disk('local')->size($storedPath),
            'uploaded_by' => $admin->id,
            'is_active' => $active,
        ]);
    }
}
