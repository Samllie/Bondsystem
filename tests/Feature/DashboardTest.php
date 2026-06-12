<?php

namespace Tests\Feature;

use App\Enums\BondRequestStatus;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Branch;
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

    public function test_requester_dashboard_includes_filter_props(): void
    {
        $requester = $this->requesterUser();

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('filters')
                ->has('statusOptions')
                ->has('bondTypeOptions')
                ->has('filterSummary')
                ->has('generatedAt')
            );
    }

    public function test_requester_dashboard_filters_bond_statistics_by_status(): void
    {
        $requester = $this->requesterUser();

        BondRequest::factory()->count(2)->create([
            'created_by' => $requester->id,
            'status' => BondRequestStatus::Pending->value,
        ]);

        BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->get(route('dashboard', ['status' => BondRequestStatus::Approved->value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.my_bonds', 1)
                ->where('stats.approved', 1)
                ->where('stats.pending', 0)
            );
    }

    public function test_admin_dashboard_filters_bond_statistics_by_date_range(): void
    {
        $admin = $this->superAdminUser();

        BondRequest::factory()->create([
            'request_date' => '2026-01-15',
            'status' => BondRequestStatus::Pending->value,
        ]);

        BondRequest::factory()->create([
            'request_date' => '2026-05-15',
            'status' => BondRequestStatus::Approved->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminDashboard')
                ->where('stats.total_bonds', 1)
                ->where('stats.approved', 1)
                ->where('stats.pending_approval', 0)
                ->where('filterSummary.0', 'From May 01, 2026')
                ->where('filterSummary.1', 'To May 31, 2026')
            );
    }

    public function test_admin_dashboard_filters_bond_statistics_by_bond_type(): void
    {
        $admin = $this->superAdminUser();
        $bondType = BondTypeMaster::factory()->create(['name' => 'Performance Bond']);

        BondRequest::factory()->create([
            'bond_type_id' => $bondType->id,
            'status' => BondRequestStatus::Pending->value,
        ]);

        BondRequest::factory()->create([
            'status' => BondRequestStatus::Pending->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['bond_type_id' => $bondType->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_bonds', 1)
                ->where('stats.pending_approval', 1)
            );
    }

    public function test_approver_dashboard_includes_branch_filter(): void
    {
        $branch = $this->makeBranch('CEB');
        $approver = $this->userWithRole(RoleSlug::Approver, $branch);

        $this->actingAs($approver)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminDashboard')
                ->where('showBranchFilter', true)
                ->has('branchOptions')
            );
    }

    public function test_approver_dashboard_filters_bond_statistics_by_branch(): void
    {
        $branchA = $this->makeBranch('CEB');
        $branchB = $this->makeBranch('MNL');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        BondRequest::factory()->create([
            'created_by' => $requesterA->id,
            'status' => BondRequestStatus::Pending->value,
        ]);

        BondRequest::factory()->create([
            'created_by' => $requesterB->id,
            'status' => BondRequestStatus::Pending->value,
        ]);

        $this->actingAs($approver)
            ->get(route('dashboard', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_bonds', 1)
                ->where('stats.pending_approval', 1)
                ->where('filterSummary.0', 'Branch: CEB Branch')
            );
    }

    public function test_requester_dashboard_does_not_include_branch_filter(): void
    {
        $requester = $this->requesterUser();

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', false)
                ->where('branchOptions', [])
            );
    }

    public function test_super_admin_dashboard_does_not_include_branch_filter(): void
    {
        $branch = $this->makeBranch('MNL');
        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin, $branch);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminDashboard')
                ->where('showBranchFilter', false)
                ->where('branchOptions', [])
            );
    }

    public function test_requester_dashboard_table_view_returns_paginated_bond_records(): void
    {
        $requester = $this->requesterUser();

        BondRequest::factory()->count(3)->create([
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->get(route('dashboard', ['view' => 'table']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('filters.view', 'table')
                ->has('bondRecords.data', 3)
                ->has('transactionRecords')
            );
    }

    public function test_admin_dashboard_table_view_returns_paginated_bond_records(): void
    {
        $admin = $this->superAdminUser();

        BondRequest::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('dashboard', ['view' => 'table']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminDashboard')
                ->where('filters.view', 'table')
                ->has('bondRecords.data', 2)
            );
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

    private function superAdminUser(): User
    {
        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function makeBranch(string $code): Branch
    {
        return Branch::query()->create([
            'name' => "{$code} Branch",
            'branch_code' => $code,
            'branch_city' => 'City',
            'is_active' => true,
        ]);
    }

    private function userWithRole(RoleSlug $slug, Branch $branch): User
    {
        $role = Role::where('slug', $slug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
