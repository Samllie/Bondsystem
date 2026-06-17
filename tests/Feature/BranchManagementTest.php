<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Maintenance\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_branch_with_branch_city(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('maintenance.branches.store'), [
            'name' => 'Test Branch',
            'branch_code' => 'TST',
            'branch_city' => 'Cebu',
            'address' => '123 Test Street',
            'contact' => '09171234567',
            'notary_price' => 1500,
            'minimum_balance' => 2500,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.branches.index'));

        $this->assertDatabaseHas('branches', [
            'name' => 'Test Branch',
            'branch_code' => 'TST',
            'branch_city' => 'Cebu',
            'address' => '123 Test Street',
            'minimum_balance' => 2500,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_branch_city(): void
    {
        $admin = $this->adminUser();
        $branch = Branch::query()->create([
            'name' => 'Existing Branch',
            'branch_code' => 'EXB',
            'branch_city' => 'Manila',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('maintenance.branches.update', $branch), [
            'name' => 'Existing Branch',
            'branch_code' => 'EXB',
            'branch_city' => 'Quezon City',
            'address' => '456 Updated Avenue',
            'contact' => null,
            'notary_price' => null,
            'minimum_balance' => 1500,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.branches.index'));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'branch_city' => 'Quezon City',
            'address' => '456 Updated Avenue',
            'minimum_balance' => 1500,
        ]);
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
