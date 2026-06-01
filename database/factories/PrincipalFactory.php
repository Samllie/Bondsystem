<?php

namespace Database\Factories;

use App\Models\Principal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Principal>
 */
class PrincipalFactory extends Factory
{
    protected $model = Principal::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company().' '.fake()->companySuffix(),
            'address' => fake()->address(),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
