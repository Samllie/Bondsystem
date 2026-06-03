<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Maintenance\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BranchSeeder::class);
    }

    public function test_super_admin_can_view_users_index(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_requester_cannot_access_users_index(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester);

        $this->actingAs($requester)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_branch_and_account_level(): void
    {
        $admin = $this->superAdmin();
        $requesterRole = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::where('name', 'Cebu Branch')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Bond User',
            'email' => 'newuser@sterling.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $requesterRole->id,
            'branch_id' => $branch->id,
            'branch_city' => 'Cebu',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@sterling.test',
            'name' => 'New Bond User',
            'role_id' => $requesterRole->id,
            'branch_id' => $branch->id,
            'branch_city' => 'Cebu',
            'is_active' => true,
        ]);
    }

    public function test_non_super_admin_cannot_assign_super_admin_role(): void
    {
        $encoder = $this->userWithRole(RoleSlug::Encoder);
        $superAdminRole = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        $encoder->role->permissions()->attach(
            Permission::whereIn('slug', ['users.view', 'users.manage'])->pluck('id')
        );

        $this->actingAs($encoder)->post(route('users.store'), [
            'name' => 'Bad Admin',
            'email' => 'badadmin@sterling.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ])->assertSessionHasErrors('role_id');
    }

    private function superAdmin(): User
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
}
