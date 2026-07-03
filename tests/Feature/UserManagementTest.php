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
use Illuminate\Support\Facades\Hash;
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
            'email' => 'newuser@sterling-insurance.com.ph',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $requesterRole->id,
            'branch_id' => $branch->id,
            'branch_city' => 'Cebu',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@sterling-insurance.com.ph',
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
            'email' => 'badadmin@sterling-insurance.com.ph',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ])->assertSessionHasErrors('role_id');
    }

    public function test_user_email_must_use_sterling_insurance_domain(): void
    {
        $admin = $this->superAdmin();
        $requesterRole = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'External User',
            'email' => 'external@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $requesterRole->id,
            'is_active' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_view_user_edit_form(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'role_id' => Role::where('slug', RoleSlug::Requester->value)->firstOrFail()->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('users.edit', $user))
            ->assertOk();
    }

    public function test_super_admin_can_update_user_details(): void
    {
        $admin = $this->superAdmin();
        $requesterRole = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::where('name', 'Cebu Branch')->firstOrFail();
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'olduser@sterling-insurance.com.ph',
            'role_id' => $requesterRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'updated@sterling-insurance.com.ph',
            'role_id' => $requesterRole->id,
            'branch_id' => $branch->id,
            'branch_city' => 'Cebu',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@sterling-insurance.com.ph',
            'branch_id' => $branch->id,
            'branch_city' => 'Cebu',
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_update_user_password(): void
    {
        $admin = $this->superAdmin();
        $requesterRole = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'passworduser@sterling-insurance.com.ph',
            'role_id' => $requesterRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'role_id' => $requesterRole->id,
            'is_active' => true,
        ])->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
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
