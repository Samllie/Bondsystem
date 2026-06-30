<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_create_signatory_with_png_signature(): void
    {
        $admin = $this->adminUser();

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = $this->actingAs($admin)->post(route('maintenance.signatories.store'), [
            'name' => 'Juan Dela Cruz',
            'position' => 'President',
            'tin' => '123-456-789-0000',
            'signature' => UploadedFile::fake()->createWithContent('signature.png', $png, 'image/png'),
        ]);

        $response->assertRedirect(route('maintenance.signatories.index'));

        $signatory = Signatory::query()->first();
        $this->assertNotNull($signatory);
        $this->assertSame('Juan Dela Cruz', $signatory->name);
        $this->assertSame('President', $signatory->position);
        $this->assertSame('123-456-789-0000', $signatory->tin);
        $this->assertNotNull($signatory->signature_path);
        Storage::disk('public')->assertExists($signatory->signature_path);
    }

    public function test_signature_must_be_png(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('maintenance.signatories.store'), [
            'name' => 'Jane Doe',
            'position' => 'CFO',
            'tin' => '111-222-333-0000',
            'signature' => UploadedFile::fake()->create('signature.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('signature');

        $this->assertDatabaseCount('signatories', 0);
    }

    public function test_admin_can_update_signatory_without_reuploading_signature(): void
    {
        $admin = $this->adminUser();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)->post(route('maintenance.signatories.store'), [
            'name' => 'Juan Dela Cruz',
            'position' => 'President',
            'tin' => '123-456-789-0000',
            'signature' => UploadedFile::fake()->createWithContent('signature.png', $png, 'image/png'),
        ])->assertRedirect();

        $signatory = Signatory::query()->firstOrFail();
        $originalSignaturePath = $signatory->signature_path;

        $this->actingAs($admin)->post(route('maintenance.signatories.update', $signatory), [
            '_method' => 'PUT',
            'name' => 'Juan Dela Cruz Jr.',
            'position' => 'Vice President',
            'tin' => '465-214-874-0000',
        ])->assertRedirect(route('maintenance.signatories.index'));

        $signatory->refresh();

        $this->assertSame('Juan Dela Cruz Jr.', $signatory->name);
        $this->assertSame('Vice President', $signatory->position);
        $this->assertSame('465-214-874-0000', $signatory->tin);
        $this->assertSame($originalSignaturePath, $signatory->signature_path);
    }

    private function adminUser(): User
    {
        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
