<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_handles_string_bond_type_values_without_crashing(): void
    {
        $requester = $this->requesterUser();

        BondRequest::factory()->create([
            'created_by' => $requester->id,
            'bond_type' => 'legacy-custom-bond',
        ]);

        $response = $this->actingAs($requester)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    private function requesterUser(): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
