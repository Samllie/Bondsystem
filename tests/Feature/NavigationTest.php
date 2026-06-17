<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_requester_sees_verify_confirmation_in_navigation(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester);

        $response = $this->actingAs($requester)->get(route('dashboard'));

        $navigation = $response->original->getData()['page']['props']['navigation'];
        $verifyLink = collect($navigation)->firstWhere('name', 'Verify Confirmation');

        $this->assertNotNull($verifyLink);
        $this->assertSame(route('certificate-verification.search'), $verifyLink['href']);
    }

    public function test_super_admin_sees_verify_confirmation_in_navigation(): void
    {
        $admin = $this->userWithRole(RoleSlug::SuperAdmin);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $navigation = $response->original->getData()['page']['props']['navigation'];
        $verifyLink = collect($navigation)->firstWhere('name', 'Verify Confirmation');

        $this->assertNotNull($verifyLink);
        $this->assertSame(route('certificate-verification.search'), $verifyLink['href']);
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
