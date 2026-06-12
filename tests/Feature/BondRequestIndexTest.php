<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BondRequestIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_index_includes_requester_and_approver_names(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester);
        $approver = $this->userWithRole(RoleSlug::Approver);
        $signatory = Signatory::factory()->create(['is_active' => true]);

        $pendingBond = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        $approvedBond = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'approved_by' => $approver->id,
            'signatory_id' => $signatory->id,
        ]);

        $response = $this->actingAs($approver)->get(route('bond-requests.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BondRequests/Index')
            ->has('bondRequests.data', 2)
            ->where('bondRequests.data', function ($records) use ($requester, $approver, $pendingBond, $approvedBond) {
                $pending = collect($records)->firstWhere('id', $pendingBond->id);
                $approved = collect($records)->firstWhere('id', $approvedBond->id);

                return $pending['creator']['name'] === $requester->name
                    && $pending['approver'] === null
                    && $approved['creator']['name'] === $requester->name
                    && $approved['approver']['name'] === $approver->name;
            })
        );
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
