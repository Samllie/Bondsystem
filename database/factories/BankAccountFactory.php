<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'bank_name' => fake()->company(),
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->company(),
            'branch' => fake()->city(),
            'is_active' => true,
        ];
    }
}
