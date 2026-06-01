<?php

namespace Database\Factories;

use App\Models\Maintenance\BondTypeMaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BondTypeMaster>
 */
class BondTypeMasterFactory extends Factory
{
    protected $model = BondTypeMaster::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
