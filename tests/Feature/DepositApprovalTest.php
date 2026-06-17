<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\Maintenance\Branch;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_approver_can_download_deposit_receipt(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();
        $receiptPath = 'receipts/deposit-receipt.pdf';
        Storage::disk('local')->put($receiptPath, 'fake receipt content');

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'receipt_path' => $receiptPath,
        ]);

        $this->actingAs($approver)
            ->get(route('payments.deposits.download-receipt', $deposit))
            ->assertOk()
            ->assertDownload('deposit-'.$deposit->id.'-receipt.pdf');
    }

    public function test_requester_can_download_own_deposit_receipt(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $bankAccount = BankAccount::factory()->create();
        $receiptPath = 'receipts/my-receipt.png';
        Storage::disk('local')->put($receiptPath, 'fake receipt content');

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'receipt_path' => $receiptPath,
        ]);

        $this->actingAs($requester)
            ->get(route('payments.deposits.download-receipt', $deposit))
            ->assertOk()
            ->assertDownload('deposit-'.$deposit->id.'-receipt.png');
    }

    public function test_requester_cannot_download_another_users_receipt(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $otherRequester = $this->createUser(RoleSlug::Requester);
        $bankAccount = BankAccount::factory()->create();
        $receiptPath = 'receipts/other-receipt.pdf';
        Storage::disk('local')->put($receiptPath, 'fake receipt content');

        $deposit = Deposit::factory()->create([
            'user_id' => $otherRequester->id,
            'bank_account_id' => $bankAccount->id,
            'receipt_path' => $receiptPath,
        ]);

        $this->actingAs($requester)
            ->get(route('payments.deposits.download-receipt', $deposit))
            ->assertForbidden();
    }

    public function test_approving_a_deposit_credits_the_branch_fund(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, branchBalance: 1000);
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
        $this->assertEquals(6000, (float) $requester->branch->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $requester->id,
            'branch_id' => $requester->branch_id,
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

    public function test_deposit_by_one_user_increases_shared_branch_fund_for_all_users(): void
    {
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'address' => 'Branch City',
            'notary_price' => 500,
            'balance' => 1000,
            'is_active' => true,
        ]);

        $firstRequester = $this->createUser(RoleSlug::Requester, branch: $branch);
        $secondRequester = $this->createUser(RoleSlug::Requester, branch: $branch);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $firstRequester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 2500,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)->post(route('payments.deposits.approve', $deposit))->assertRedirect();

        $this->assertEquals(3500, (float) $branch->fresh()->balance);
        $this->assertEquals(3500, (float) $firstRequester->branchBalance());
        $this->assertEquals(3500, (float) $secondRequester->branchBalance());
    }

    public function test_approving_deposits_for_different_users_assigns_unique_transaction_numbers(): void
    {
        $firstRequester = $this->createUser(RoleSlug::Requester, branchBalance: 0);
        $secondRequester = $this->createUser(RoleSlug::Requester, branchBalance: 0);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $firstDeposit = Deposit::factory()->create([
            'user_id' => $firstRequester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 1000,
            'status' => DepositStatus::Pending,
        ]);

        $secondDeposit = Deposit::factory()->create([
            'user_id' => $secondRequester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 2000,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)->post(route('payments.deposits.approve', $firstDeposit))->assertRedirect();
        $this->actingAs($approver)->post(route('payments.deposits.approve', $secondDeposit))->assertRedirect();

        $numbers = Transaction::query()->orderBy('id')->pluck('transaction_number');

        $this->assertCount(2, $numbers);
        $this->assertCount(2, $numbers->unique());
        $this->assertNotSame($numbers[0], $numbers[1]);
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
        $requester = $this->createUser(RoleSlug::Requester, branchBalance: 0);
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

        $this->assertEquals(0, (float) $requester->branch->fresh()->balance);
        $this->assertSame(0, Transaction::count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(RoleSlug $roleSlug, array $attributes = [], ?Branch $branch = null, float $branchBalance = 0): User
    {
        $role = Role::where('slug', $roleSlug->value)->firstOrFail();

        $branch ??= Branch::query()->create([
            'name' => 'Test Branch',
            'branch_code' => strtoupper(Str::random(3)),
            'address' => 'Branch City',
            'notary_price' => 500,
            'balance' => $branchBalance,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => $branch->branch_code,
            'branch_city' => 'Branch City',
            'balance' => 0,
            'is_active' => true,
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }
}
