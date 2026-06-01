<?php

namespace Database\Factories;

use App\Models\Maintenance\Signatory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signatory>
 */
class SignatoryFactory extends Factory
{
    protected $model = Signatory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'is_active' => true,
        ];
    }
}
