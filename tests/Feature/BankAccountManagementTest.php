<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_bank_accounts_index(): void
    {
        BankAccount::factory()->create(['bank_name' => 'BDO Unibank']);

        $response = $this->actingAs($this->superAdmin())->get(route('maintenance.bank-accounts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Maintenance/BankAccounts/Index')
            ->has('records.data', 1)
            ->where('records.data.0.bank_name', 'BDO Unibank')
        );
    }

    public function test_encoder_cannot_manage_bank_accounts(): void
    {
        $role = Role::where('slug', RoleSlug::Encoder->value)->firstOrFail();
        $encoder = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($encoder)->get(route('maintenance.bank-accounts.index'))->assertForbidden();
        $this->actingAs($encoder)->get(route('maintenance.bank-accounts.create'))->assertForbidden();
    }

    public function test_super_admin_can_create_bank_account(): void
    {
        $response = $this->actingAs($this->superAdmin())->post(route('maintenance.bank-accounts.store'), [
            'bank_name' => 'BPI',
            'account_number' => '9876543210',
            'account_name' => 'Sterling Insurance Company Inc.',
            'branch' => 'Ortigas Branch',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('maintenance.bank-accounts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_accounts', [
            'bank_name' => 'BPI',
            'account_number' => '9876543210',
            'account_name' => 'Sterling Insurance Company Inc.',
            'branch' => 'Ortigas Branch',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_update_bank_account(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'bank_name' => 'Metrobank',
            'account_number' => '123456789012',
        ]);

        $response = $this->actingAs($this->superAdmin())->put(route('maintenance.bank-accounts.update', $bankAccount), [
            'bank_name' => 'Metrobank Updated',
            'account_number' => '123456789012',
            'account_name' => $bankAccount->account_name,
            'branch' => 'New Branch',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('maintenance.bank-accounts.index'));

        $bankAccount->refresh();
        $this->assertSame('Metrobank Updated', $bankAccount->bank_name);
        $this->assertSame('New Branch', $bankAccount->branch);
        $this->assertFalse($bankAccount->is_active);
    }

    public function test_inactive_bank_account_is_not_available_on_deposit_create_page(): void
    {
        BankAccount::factory()->create(['is_active' => true, 'bank_name' => 'Active Bank']);
        BankAccount::factory()->create(['is_active' => false, 'bank_name' => 'Inactive Bank']);

        $requester = $this->requester();

        $response = $this->actingAs($requester)->get(route('payments.deposits.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('bankAccounts', 1)
            ->where('bankAccounts.0.bank_name', 'Active Bank')
        );
    }

    public function test_deposit_cannot_use_inactive_bank_account(): void
    {
        $bankAccount = BankAccount::factory()->create(['is_active' => false]);
        $requester = $this->requester();

        $this->actingAs($requester)->post(route('payments.deposits.store'), [
            'bank_account_id' => $bankAccount->id,
            'amount' => 1000,
            'reference_number' => 'REF-001',
            'deposit_date' => now()->toDateString(),
        ])->assertSessionHasErrors('bank_account_id');
    }

    public function test_bank_account_with_deposits_cannot_be_deleted(): void
    {
        $bankAccount = BankAccount::factory()->create();
        Deposit::factory()->create(['bank_account_id' => $bankAccount->id]);

        $response = $this->actingAs($this->superAdmin())
            ->from(route('maintenance.bank-accounts.index'))
            ->delete(route('maintenance.bank-accounts.destroy', $bankAccount));

        $response->assertRedirect(route('maintenance.bank-accounts.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bank_accounts', ['id' => $bankAccount->id]);
    }

    public function test_super_admin_can_delete_unused_bank_account(): void
    {
        $bankAccount = BankAccount::factory()->create();

        $response = $this->actingAs($this->superAdmin())
            ->delete(route('maintenance.bank-accounts.destroy', $bankAccount));

        $response->assertRedirect(route('maintenance.bank-accounts.index'));
        $this->assertDatabaseMissing('bank_accounts', ['id' => $bankAccount->id]);
    }

    public function test_account_number_must_be_unique(): void
    {
        BankAccount::factory()->create(['account_number' => '001234567890']);

        $this->actingAs($this->superAdmin())->post(route('maintenance.bank-accounts.store'), [
            'bank_name' => 'Duplicate Bank',
            'account_number' => '001234567890',
            'account_name' => 'Sterling Insurance Company Inc.',
            'branch' => 'Main',
            'is_active' => true,
        ])->assertSessionHasErrors('account_number');
    }

    private function superAdmin(): User
    {
        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function requester(): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
