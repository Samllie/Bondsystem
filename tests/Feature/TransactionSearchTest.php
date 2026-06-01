<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Enums\TransactionType;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_transactions_can_be_searched_by_transaction_number(): void
    {
        $user = $this->createUser(RoleSlug::Requester);

        $matching = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Credit->value,
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
            'description' => 'Test credit',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Credit->value,
            'amount' => 500,
            'balance_before' => 1000,
            'balance_after' => 1500,
            'description' => 'Other credit',
        ]);

        $this->actingAs($user)
            ->get(route('payments.transactions.index', ['search' => $matching->transaction_number]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.transaction_number', $matching->transaction_number)
            );
    }

    private function createUser(RoleSlug $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
