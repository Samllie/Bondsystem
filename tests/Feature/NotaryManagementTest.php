<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Maintenance\Notary;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotaryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_create_notary_with_png_seal(): void
    {
        $admin = $this->adminUser();

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Atty. Maria Santos',
            'commission_number' => '2024-001-NCR',
            'tin' => '123-456-789-000',
            'signature' => UploadedFile::fake()->createWithContent('seal.png', $png, 'image/png'),
        ]);

        $response->assertRedirect(route('maintenance.notaries.index'));

        $notary = Notary::query()->first();
        $this->assertNotNull($notary);
        $this->assertSame('Atty. Maria Santos', $notary->name);
        $this->assertSame('2024-001-NCR', $notary->commission_number);
        $this->assertSame('123-456-789-000', $notary->tin);
        $this->assertNotNull($notary->signature_path);
        Storage::disk('public')->assertExists($notary->signature_path);
    }

    public function test_notary_seal_must_be_png(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Jane Doe',
            'commission_number' => '2024-002',
            'tin' => '111-222-333',
            'signature' => UploadedFile::fake()->create('seal.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('signature');

        $this->assertDatabaseCount('notaries', 0);
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
