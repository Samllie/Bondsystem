<?php

namespace Database\Factories;

use App\Enums\BondRequestStatus;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Principal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BondRequest>
 */
class BondRequestFactory extends Factory
{
    protected $model = BondRequest::class;

    public function definition(): array
    {
        return [
            'bond_number' => 'BND-'.fake()->unique()->numerify('######'),
            'bond_type_id' => BondTypeMaster::factory(),
            'bond_type' => 'performance',
            'principal_id' => Principal::factory(),
            'obligee_id' => fake()->numberBetween(1, 99999),
            'obligee_name' => fake()->company(),
            'address_1' => fake()->streetAddress(),
            'amount' => fake()->randomFloat(2, 10000, 5000000),
            'amount_in_words' => 'Ten Thousand Pesos Only',
            'project_name' => fake()->words(3, true),
            'description' => fake()->sentence(12),
            'expiry_date' => fake()->dateTimeBetween('+3 months', '+2 years'),
            'request_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'signatory_id' => Signatory::factory(),
            'signatory_position' => fake()->jobTitle(),
            'notary_id' => Notary::factory(),
            'doc_no' => fake()->numerify('DOC-####'),
            'page_no' => (string) fake()->numberBetween(1, 500),
            'book_no' => (string) fake()->numberBetween(1, 100),
            'series_year' => (string) fake()->year(),
            'status' => fake()->randomElement(BondRequestStatus::cases())->value,
            'remarks' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => BondRequestStatus::Pending->value]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BondRequestStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }
}
