<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_approving_a_deposit_credits_the_requester_balance(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, ['balance' => 1000]);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000,
            'status' => DepositStatus::Pending,
        ]);

        $response = $this->actingAs($approver)->post(route('payments.deposits.approve', $deposit));

        $response->assertRedirect();

        $requester->refresh();
        $deposit->refresh();

        $this->assertSame(DepositStatus::Approved, $deposit->status);
        $this->assertEquals(6000, (float) $requester->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $requester->id,
            'type' => 'credit',
            'amount' => 5000,
            'balance_before' => 1000,
            'balance_after' => 6000,
            'subject_type' => Deposit::class,
            'subject_id' => $deposit->id,
        ]);

        $transaction = Transaction::first();
        $this->assertNotNull($transaction->transaction_number);
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-\d{5}$/', $transaction->transaction_number);
        $response->assertSessionHas('success');
    }

    public function test_pending_deposit_show_page_exposes_approve_action_for_approver(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)
            ->get(route('payments.deposits.show', $deposit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Deposits/Show')
                ->where('canApprove', true)
            );
    }

    public function test_approving_a_deposit_cannot_be_done_twice(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, ['balance' => 0]);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->approved()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000,
            'approved_by' => $approver->id,
        ]);

        $this->actingAs($approver)
            ->post(route('payments.deposits.approve', $deposit))
            ->assertStatus(422);

        $requester->refresh();
        $this->assertEquals(0, (float) $requester->balance);
        $this->assertSame(0, Transaction::count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(RoleSlug $roleSlug, array $attributes = []): User
    {
        $role = Role::where('slug', $roleSlug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }
}
