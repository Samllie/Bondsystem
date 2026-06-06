<?php

namespace Database\Factories;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
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
        $bondTypeFactory = BondTypeMaster::factory();

        return [
            'bond_number' => '0000000',
            'bond_type_id' => $bondTypeFactory,
            'bond_type' => 'performance',
            'principal_id' => Principal::factory(),
            'obligee_id' => fake()->numberBetween(1, 99999),
            'obligee_name' => fake()->company(),
            'address_1' => fake()->streetAddress(),
            'amount' => fake()->randomFloat(2, 10000, 5000000),
            'amount_in_words' => 'Ten Thousand Pesos Only',
            'project_name' => fake()->words(3, true),
            'date_issued' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'inception_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'attention' => fake()->optional()->name(),
            'certificate_type' => fake()->randomElement(CertificateType::cases())->value,
            'description' => fake()->sentence(12),
            'expiry_date' => fake()->boolean(70)
                ? fake()->dateTimeBetween('+3 months', '+2 years')->format('Y-m-d')
                : 'until fully recouped and liquidated is valid',
            'request_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'signatory_id' => null,
            'signatory_position' => null,
            'notary_id' => null,
            'doc_no' => null,
            'page_no' => null,
            'book_no' => null,
            'series_year' => null,
            'status' => fake()->randomElement(BondRequestStatus::cases())->value,
            'remarks' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (BondRequest $bondRequest): void {
            if ($bondRequest->certificate_type === CertificateType::CarCertificate) {
                return;
            }

            $bondRequest->load('bondTypeMaster');

            if ($bondRequest->bondTypeMaster) {
                $bondRequest->updateQuietly([
                    'bond_number' => $bondRequest->bondTypeMaster->code,
                    'bond_type' => $bondRequest->bondTypeMaster->name,
                ]);
            }
        });
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

    public function carCertificate(string $branchCode = 'MKT', string $serial = '0072056'): static
    {
        $car = sprintf('CAR-%s-%s', strtoupper($branchCode), str_pad(preg_replace('/\D/', '', $serial) ?: '0', 7, '0', STR_PAD_LEFT));

        return $this->state(fn () => [
            'certificate_type' => CertificateType::CarCertificate->value,
            'car' => $car,
            'bond_number' => $car,
            'bond_type' => 'CAR',
            'bond_type_id' => null,
            'authorized_representative' => fake()->name(),
            'tin' => sprintf(
                '%s-%s-%s-0000',
                fake()->numerify('###'),
                fake()->numerify('###'),
                fake()->numerify('###'),
            ),
        ]);
    }
}
