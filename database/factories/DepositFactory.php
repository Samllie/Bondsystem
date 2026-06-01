<?php

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_account_id' => BankAccount::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'reference_number' => fake()->numerify('REF-########'),
            'receipt_path' => 'receipts/test-receipt.png',
            'deposit_date' => now()->toDateString(),
            'status' => DepositStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => DepositStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
