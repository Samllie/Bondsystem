<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\BondRequest;
use App\Models\Deposit;
use App\Models\Maintenance\Branch;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_approver_sees_all_bond_requests_by_default(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        BondRequest::factory()->create(['created_by' => $requesterA->id]);
        BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($approver)
            ->get(route('bond-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', true)
                ->has('bondRequests.data', 2)
            );
    }

    public function test_approver_can_filter_bond_requests_by_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $bondA = BondRequest::factory()->create(['created_by' => $requesterA->id]);
        BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($approver)
            ->get(route('bond-requests.index', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bondRequests.data', 1)
                ->where('bondRequests.data.0.id', $bondA->id)
            );
    }

    public function test_encoder_only_sees_bond_requests_from_own_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $bondA = BondRequest::factory()->create(['created_by' => $requesterA->id]);
        BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($encoder)
            ->get(route('bond-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', false)
                ->has('bondRequests.data', 1)
                ->where('bondRequests.data.0.id', $bondA->id)
            );
    }

    public function test_requester_only_sees_their_own_bond_requests(): void
    {
        $branch = $this->makeBranch('AAA');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branch);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branch);

        $bondA = BondRequest::factory()->create(['created_by' => $requesterA->id]);
        BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($requesterA)
            ->get(route('bond-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', false)
                ->has('bondRequests.data', 1)
                ->where('bondRequests.data.0.id', $bondA->id)
            );
    }

    public function test_super_admin_sees_all_bond_requests_without_branch_filter(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $superAdmin = $this->userWithRole(RoleSlug::SuperAdmin, $branchA);

        BondRequest::factory()->create(['created_by' => $requesterA->id]);
        BondRequest::factory()->create(['created_by' => $requesterB->id]);

        $this->actingAs($superAdmin)
            ->get(route('bond-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', false)
                ->has('bondRequests.data', 2)
            );
    }

    public function test_approver_sees_all_transactions_by_default(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);

        $this->createTransaction($requesterA);
        $this->createTransaction($requesterB);

        $this->actingAs($approver)
            ->get(route('payments.transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', true)
                ->has('transactions.data', 2)
            );
    }

    public function test_encoder_only_sees_transactions_from_own_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branchA);

        $this->createTransaction($requesterA);
        $this->createTransaction($requesterB);

        $this->actingAs($encoder)
            ->get(route('payments.transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', false)
                ->has('transactions.data', 1)
            );
    }

    public function test_approver_can_filter_deposits_by_branch(): void
    {
        $branchA = $this->makeBranch('AAA');
        $branchB = $this->makeBranch('BBB');
        $requesterA = $this->userWithRole(RoleSlug::Requester, $branchA);
        $requesterB = $this->userWithRole(RoleSlug::Requester, $branchB);
        $approver = $this->userWithRole(RoleSlug::Approver, $branchA);
        $bankAccount = BankAccount::factory()->create();

        $depositA = Deposit::factory()->create([
            'user_id' => $requesterA->id,
            'bank_account_id' => $bankAccount->id,
            'status' => DepositStatus::Pending,
        ]);

        Deposit::factory()->create([
            'user_id' => $requesterB->id,
            'bank_account_id' => $bankAccount->id,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)
            ->get(route('payments.deposits.index', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showBranchFilter', true)
                ->has('deposits.data', 1)
                ->where('deposits.data.0.id', $depositA->id)
            );
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

    private function createTransaction(User $user): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Credit->value,
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
            'description' => 'Test credit',
        ]);
    }
}
