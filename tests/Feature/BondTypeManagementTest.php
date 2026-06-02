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

    public function test_admin_can_create_bond_type_with_number_type_and_description(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('maintenance.bond-types.store'), [
            'code' => 'BND-001',
            'name' => 'Performance Bond',
            'description' => 'Bond for project performance guarantee',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.bond-types.index'));

        $this->assertDatabaseHas('bond_type_masters', [
            'code' => 'BND-001',
            'name' => 'Performance Bond',
            'description' => 'Bond for project performance guarantee',
            'is_active' => true,
        ]);
    }

    public function test_bond_type_requires_number_only_and_allows_empty_description(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('maintenance.bond-types.store'), [
            'code' => '',
            'name' => 'Bid Bond',
            'description' => '',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['code']);
        $response->assertSessionDoesntHaveErrors(['description']);
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
