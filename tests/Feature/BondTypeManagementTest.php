<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BondTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_bond_type_with_registered_bond_number_format(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('maintenance.bond-types.store'), [
            'code' => 'G(42)',
            'name' => 'Retention Money Bond',
            'description' => 'Retention money guarantee bond',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.bond-types.index'));

        $this->assertDatabaseHas('bond_type_masters', [
            'code' => 'G(42)',
            'name' => 'Retention Money Bond',
            'description' => 'Retention money guarantee bond',
            'is_active' => true,
        ]);
    }

    public function test_bond_type_allows_empty_description_without_bond_serial(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('maintenance.bond-types.store'), [
            'code' => 'ABC123',
            'name' => 'Bid Bond',
            'description' => '',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.bond-types.index'));
        $response->assertSessionDoesntHaveErrors(['code', 'description']);
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
