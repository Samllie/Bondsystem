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
            'tin' => '123-456-789-0000',
            'signature' => UploadedFile::fake()->createWithContent('seal.png', $png, 'image/png'),
        ]);

        $response->assertRedirect(route('maintenance.notaries.index'));

        $notary = Notary::query()->first();
        $this->assertNotNull($notary);
        $this->assertSame('Atty. Maria Santos', $notary->name);
        $this->assertSame('2024-001-NCR', $notary->commission_number);
        $this->assertSame('123-456-789-0000', $notary->tin);
        $this->assertNotNull($notary->signature_path);
        Storage::disk('public')->assertExists($notary->signature_path);
    }

    public function test_notary_tin_must_follow_four_part_format(): void
    {
        $admin = $this->adminUser();

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Bad Tin',
            'commission_number' => '2024-003',
            'tin' => '123-456-789',
            'signature' => UploadedFile::fake()->createWithContent('seal.png', $png, 'image/png'),
        ])->assertSessionHasErrors('tin');

        $this->assertDatabaseCount('notaries', 0);
    }

    public function test_admin_can_update_notary_without_reuploading_seal(): void
    {
        $admin = $this->adminUser();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Atty. Maria Santos',
            'commission_number' => '2024-001-NCR',
            'tin' => '123-456-789-0000',
            'signature' => UploadedFile::fake()->createWithContent('seal.png', $png, 'image/png'),
        ])->assertRedirect();

        $notary = Notary::query()->firstOrFail();
        $originalSignaturePath = $notary->signature_path;

        $this->actingAs($admin)->post(route('maintenance.notaries.update', $notary), [
            '_method' => 'PUT',
            'name' => 'Atty. Maria Santos Updated',
            'commission_number' => '2024-002-NCR',
            'tin' => '465-214-874-0000',
        ])->assertRedirect(route('maintenance.notaries.index'));

        $notary->refresh();

        $this->assertSame('Atty. Maria Santos Updated', $notary->name);
        $this->assertSame('2024-002-NCR', $notary->commission_number);
        $this->assertSame('465-214-874-0000', $notary->tin);
        $this->assertSame($originalSignaturePath, $notary->signature_path);
    }

    public function test_notary_seal_may_not_exceed_ten_megabytes(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Big Seal',
            'commission_number' => '2024-004',
            'tin' => '123-456-789-0000',
            'signature' => UploadedFile::fake()->create('seal.png', 10 * 1024 + 1, 'image/png'),
        ])->assertSessionHasErrors('signature');

        $this->assertDatabaseCount('notaries', 0);
    }

    public function test_notary_seal_must_be_png(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('maintenance.notaries.store'), [
            'name' => 'Jane Doe',
            'commission_number' => '2024-002',
            'tin' => '111-222-333-0000',
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
