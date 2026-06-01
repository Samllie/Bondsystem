<?php

namespace Database\Seeders;

use App\Models\Maintenance\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, address: string|null, contact: string|null}>
     */
    private function branches(): array
    {
        return [
            // NCR Branches
            ['name' => 'Alabang Branch', 'address' => 'NCR', 'contact' => null],
            ['name' => 'Manila Branch I', 'address' => 'NCR', 'contact' => null],
            ['name' => 'Manila Branch II', 'address' => 'NCR', 'contact' => null],
            ['name' => 'West Avenue Branch', 'address' => 'NCR', 'contact' => null],
            ['name' => 'Cubao Branch', 'address' => 'NCR', 'contact' => null],
            ['name' => 'Makati Head Office', 'address' => 'NCR — Makati City', 'contact' => null],
            ['name' => 'Marikina Branch', 'address' => 'NCR — Marikina City', 'contact' => null],

            // Luzon Branches
            ['name' => 'Angeles Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Batangas Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Cabanatuan Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Dagupan Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Isabela Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'La Union Branch', 'address' => 'Luzon — La Union', 'contact' => null],
            ['name' => 'Laoag Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Legazpi I Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Legazpi II Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Mindoro Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Naga Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'San Fernando, Pampanga Branch', 'address' => 'Luzon — San Fernando, Pampanga', 'contact' => null],
            ['name' => 'Tuguegarao Branch', 'address' => 'Luzon', 'contact' => null],
            ['name' => 'Vigan Branch', 'address' => 'Luzon', 'contact' => null],

            // Visayas Branches
            ['name' => 'Bacolod Branch', 'address' => 'Visayas', 'contact' => null],
            ['name' => 'Cebu Branch', 'address' => 'Visayas — Cebu City', 'contact' => null],
            ['name' => 'Tacloban Branch', 'address' => 'Visayas', 'contact' => null],
            ['name' => 'Ormoc Branch', 'address' => 'Visayas', 'contact' => null],
            ['name' => 'Iloilo Branch', 'address' => 'Visayas', 'contact' => null],

            // Mindanao Branches
            ['name' => 'Butuan Branch', 'address' => 'Mindanao', 'contact' => null],
            ['name' => 'CDO (Cagayan de Oro) Branch', 'address' => 'Mindanao', 'contact' => null],
            ['name' => 'Davao I Branch', 'address' => 'Mindanao — Davao City', 'contact' => null],
            ['name' => 'Davao II Branch', 'address' => 'Mindanao — Davao City', 'contact' => null],
            ['name' => 'Gensan Branch', 'address' => 'Mindanao', 'contact' => null],
            ['name' => 'Ozamiz Branch', 'address' => 'Mindanao', 'contact' => null],
            ['name' => 'Pagadian Branch', 'address' => 'Mindanao', 'contact' => null],
        ];
    }

    public function run(): void
    {
        foreach ($this->branches() as $branch) {
            Branch::updateOrCreate(
                ['name' => $branch['name']],
                [
                    'address' => $branch['address'],
                    'contact' => $branch['contact'],
                    'is_active' => true,
                ],
            );
        }
    }
}
