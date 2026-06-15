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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotaryRoleTest extends TestCase
{
    use RefreshDatabase;

    private string $testCertPath;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        $this->testCertPath = storage_path('app/private/certificates/notary_role_test.pdf');
        $this->branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCertPath)) {
            @unlink($this->testCertPath);
        }

        parent::tearDown();
    }

    public function test_attorney_cannot_access_dashboard_or_bond_requests(): void
    {
        $attorney = $this->attorneyUser();

        $this->actingAs($attorney)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($attorney)->get(route('bond-requests.index'))->assertForbidden();
        $this->actingAs($attorney)->get(route('users.index'))->assertForbidden();
        $this->actingAs($attorney)->get(route('maintenance.signatories.index'))->assertForbidden();
    }

    public function test_attorney_cannot_access_payments(): void
    {
        $attorney = $this->attorneyUser();

        $this->actingAs($attorney)->get(route('payments.deposits.index'))->assertForbidden();
        $this->actingAs($attorney)->get(route('payments.deposits.create'))->assertForbidden();
        $this->actingAs($attorney)->get(route('payments.transactions.index'))->assertForbidden();
        $this->actingAs($attorney)->get(route('payments.histories.index'))->assertForbidden();
    }

    public function test_attorney_navigation_excludes_payments_section(): void
    {
        $attorney = $this->attorneyUser();

        $response = $this->actingAs($attorney)->get(route('certifications.index'));

        $navigation = $response->original->getData()['page']['props']['navigation'];
        $paymentSection = collect($navigation)->firstWhere('name', 'Payments');

        $this->assertNull($paymentSection);
        $this->assertCount(1, collect($navigation)->where('name', 'Certifications'));
    }

    public function test_attorney_can_access_profile_and_certifications(): void
    {
        $attorney = $this->attorneyUser();

        $this->actingAs($attorney)->get(route('profile.edit'))->assertOk();
        $this->actingAs($attorney)->get(route('certifications.index'))->assertOk();
    }

    public function test_creating_notary_user_provisions_signatory_and_notary_records(): void
    {
        $admin = $this->superAdmin();
        $notaryRole = Role::where('slug', RoleSlug::Notary->value)->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Attorney One',
            'email' => 'attorney.one@sterling-insurance.com.ph',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $notaryRole->id,
            'is_active' => true,
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'attorney.one@sterling-insurance.com.ph')->firstOrFail();

        $this->assertDatabaseHas('signatories', [
            'user_id' => $user->id,
            'name' => 'Attorney One',
        ]);

        $this->assertDatabaseHas('notaries', [
            'user_id' => $user->id,
            'name' => 'Attorney One',
        ]);
    }

    public function test_attorney_sees_all_certificates(): void
    {
        $attorney = $this->attorneyUser();
        $otherSignatory = Signatory::factory()->create(['is_active' => true]);
        $otherNotary = Notary::factory()->create(['is_active' => true]);

        $this->bondRequestWithCertificate([
            'signatory_id' => $attorney->signatory->id,
            'notary_id' => $otherNotary->id,
        ]);
        $this->bondRequestWithCertificate([
            'signatory_id' => $otherSignatory->id,
            'notary_id' => $attorney->notary->id,
        ]);
        $this->bondRequestWithCertificate([
            'signatory_id' => $otherSignatory->id,
            'notary_id' => $otherNotary->id,
        ]);

        $response = $this->actingAs($attorney)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Certifications/Index')
            ->where('context', 'attorney')
            ->where('readOnly', true)
            ->where('showBranchFilter', true)
            ->has('certificates.data', 3)
        );
    }

    public function test_attorney_can_view_and_download_any_certificate(): void
    {
        $attorney = $this->attorneyUser();
        $otherSignatory = Signatory::factory()->create(['is_active' => true]);
        $otherNotary = Notary::factory()->create(['is_active' => true]);

        $certificate = $this->bondRequestWithCertificate([
            'signatory_id' => $otherSignatory->id,
            'notary_id' => $otherNotary->id,
        ]);

        $this->actingAs($attorney)
            ->get(route('bond-requests.view-certificate', $certificate))
            ->assertOk();

        $this->actingAs($attorney)
            ->get(route('bond-requests.download-certificate', $certificate))
            ->assertOk();
    }

    public function test_attorney_can_update_profile_signatory_and_notary_details(): void
    {
        $attorney = $this->attorneyUser();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = $this->actingAs($attorney)->patch(route('profile.update'), [
            'name' => 'Updated Attorney',
            'email' => 'updated.attorney@sterling-insurance.com.ph',
            'signatory_position' => 'Vice President',
            'signatory_tin' => '111-222-333-000',
            'notary_commission_number' => 'CN-12345',
            'notary_tin' => '123-456-789-0000',
            'signatory_signature' => UploadedFile::fake()->createWithContent('signature.png', $png, 'image/png'),
            'notary_signature' => UploadedFile::fake()->createWithContent('seal.png', $png, 'image/png'),
        ]);

        $response->assertRedirect(route('profile.edit'));

        $attorney->refresh();
        $attorney->load(['signatory', 'notary']);

        $this->assertSame('Updated Attorney', $attorney->name);
        $this->assertSame('updated.attorney@sterling-insurance.com.ph', $attorney->email);
        $this->assertSame('Vice President', $attorney->signatory->position);
        $this->assertSame('111-222-333-000', $attorney->signatory->tin);
        $this->assertSame('CN-12345', $attorney->notary->commission_number);
        $this->assertSame('123-456-789-0000', $attorney->notary->tin);
        $this->assertNotNull($attorney->signatory->signature_path);
        $this->assertNotNull($attorney->notary->signature_path);
        Storage::disk('public')->assertExists($attorney->signatory->signature_path);
        Storage::disk('public')->assertExists($attorney->notary->signature_path);
    }

    public function test_signatory_index_shows_linked_account_email(): void
    {
        $admin = $this->superAdmin();
        $attorney = $this->attorneyUser();

        $response = $this->actingAs($admin)->get(route('maintenance.signatories.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Maintenance/Signatories/Index')
            ->where('records.data.0.user.email', $attorney->email)
        );
    }

    private function superAdmin(): User
    {
        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function attorneyUser(): User
    {
        $role = Role::where('slug', RoleSlug::Notary->value)->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Signatory::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        Notary::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        return $user->fresh(['signatory', 'notary']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function bondRequestWithCertificate(array $overrides = []): BondRequest
    {
        $relativePath = 'private/certificates/notary_role_test.pdf';

        if (! is_dir(dirname($this->testCertPath))) {
            mkdir(dirname($this->testCertPath), 0755, true);
        }

        file_put_contents($this->testCertPath, '%PDF-1.4 fake pdf content');

        $signatory = $overrides['signatory_id'] ?? Signatory::factory()->create(['is_active' => true])->id;
        $notary = $overrides['notary_id'] ?? Notary::factory()->create(['is_active' => true])->id;
        unset($overrides['signatory_id'], $overrides['notary_id']);

        $creator = User::factory()->create(['branch_id' => $this->branch->id]);

        return BondRequest::factory()
            ->approved()
            ->create([
                'certificate_type' => CertificateType::BondCertificate->value,
                'signatory_id' => $signatory,
                'notary_id' => $notary,
                'created_by' => $creator->id,
                'certificate_path' => $relativePath,
                'tin' => '123-456-789-0000',
                ...$overrides,
            ]);
    }
}
