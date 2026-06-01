<?php

namespace Database\Factories;

use App\Models\Maintenance\Notary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notary>
 */
class NotaryFactory extends Factory
{
    protected $model = Notary::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'commission_number' => fake()->numerify('####-###-NCR'),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'is_active' => true,
        ];
    }
}
