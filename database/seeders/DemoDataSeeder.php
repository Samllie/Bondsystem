<?php

namespace Database\Seeders;

use App\Enums\BondRequestStatus;
use App\Enums\BondType;
use App\Models\BondRequest;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $obligees = Obligee::factory()->count(8)->create();
        $principals = Principal::factory()->count(8)->create();
        $admin = User::where('email', 'admin@sterling.test')->first();
        $requester = User::where('email', 'requester@sterling.test')->first();

        $statuses = [
            BondRequestStatus::Pending,
            BondRequestStatus::Pending,
            BondRequestStatus::Approved,
            BondRequestStatus::Approved,
            BondRequestStatus::Notarized,
            BondRequestStatus::Rejected,
            BondRequestStatus::Draft,
            BondRequestStatus::Pending,
        ];

        foreach ($statuses as $index => $status) {
            BondRequest::create([
                'bond_number' => 'SIC-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'bond_type' => BondType::cases()[$index % count(BondType::cases())]->value,
                'principal_id' => $principals->random()->id,
                'obligee_id' => $obligees->random()->id,
                'amount' => fake()->randomFloat(2, 50000, 2500000),
                'description' => 'Insurance bond request for '.fake()->words(3, true),
                'expiry_date' => now()->addMonths(12),
                'request_date' => now()->subDays(rand(1, 60)),
                'status' => $status->value,
                'remarks' => fake()->optional()->sentence(),
                'created_by' => $requester?->id ?? $admin?->id,
            ]);
        }

        ActivityLogger::log('seed', 'Demo data seeded for Sterling Insurance Bond System');
    }
}
