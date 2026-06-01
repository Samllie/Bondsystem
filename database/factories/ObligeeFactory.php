<?php

namespace Database\Factories;

use App\Models\Obligee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obligee>
 */
class ObligeeFactory extends Factory
{
    protected $model = Obligee::class;

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
