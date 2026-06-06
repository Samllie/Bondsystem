<?php

namespace Database\Seeders;

use App\Models\Maintenance\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, branch_code: string, branch_city: string|null, contact: string|null}>
     */
    private function branches(): array
    {
        return [
            ['name' => 'Main Office- Makati', 'branch_code' => 'MAK', 'branch_city' => 'Makati', 'contact' => null],
            ['name' => 'Alabang Branch', 'branch_code' => 'ALB', 'branch_city' => 'Muntinlupa', 'contact' => null],
            ['name' => 'Manila I Branch', 'branch_code' => 'MNL', 'branch_city' => 'Manila', 'contact' => null],
            ['name' => 'Manila II Branch', 'branch_code' => 'MNA', 'branch_city' => 'Manila', 'contact' => null],
            ['name' => 'West Avenue Branch', 'branch_code' => 'WST', 'branch_city' => 'Quezon', 'contact' => null],
            ['name' => 'Cubao Branch', 'branch_code' => 'CUB', 'branch_city' => 'Quezon', 'contact' => null],
            ['name' => 'Angeles Branch', 'branch_code' => 'ANG', 'branch_city' => 'Angeles', 'contact' => null],
            ['name' => 'Batangas Branch', 'branch_code' => 'BTG', 'branch_city' => 'Lipa', 'contact' => null],
            ['name' => 'Cabanatuan Branch', 'branch_code' => 'CBN', 'branch_city' => 'Cabanatuan', 'contact' => null],
            ['name' => 'Dagupan Branch', 'branch_code' => 'DGP', 'branch_city' => 'Dagupan', 'contact' => null],
            ['name' => 'Isabela Branch', 'branch_code' => 'ISB', 'branch_city' => 'Santiago', 'contact' => null],
            ['name' => 'La Union Branch', 'branch_code' => 'LAU', 'branch_city' => 'San Fernando', 'contact' => null],
            ['name' => 'Laoag Branch', 'branch_code' => 'LAO', 'branch_city' => 'Laoag', 'contact' => null],
            ['name' => 'Legazpi I Branch', 'branch_code' => 'LGP', 'branch_city' => 'Legazpi', 'contact' => null],
            ['name' => 'Legazpi II Branch', 'branch_code' => 'LGZ', 'branch_city' => 'Legazpi', 'contact' => null],
            ['name' => 'Mindoro Branch', 'branch_code' => 'MIN', 'branch_city' => 'Calapan', 'contact' => null],
            ['name' => 'Naga Branch', 'branch_code' => 'NGA', 'branch_city' => 'Naga', 'contact' => null],
            ['name' => 'San Fernando Branch', 'branch_code' => 'SNF', 'branch_city' => 'San Fernando', 'contact' => null],
            ['name' => 'Tuguegarao Branch', 'branch_code' => 'TUG', 'branch_city' => 'Tuguegarao', 'contact' => null],
            ['name' => 'Vigan Branch', 'branch_code' => 'VIG', 'branch_city' => 'Vigan', 'contact' => null],
            ['name' => 'Bacolod Branch', 'branch_code' => 'BCD', 'branch_city' => 'Bacolod', 'contact' => null],
            ['name' => 'Cebu Branch', 'branch_code' => 'CEB', 'branch_city' => 'Cebu', 'contact' => null],
            ['name' => 'Tacloban Branch', 'branch_code' => 'TAC', 'branch_city' => 'Tacloban', 'contact' => null],
            ['name' => 'Ormoc Branch', 'branch_code' => 'ORM', 'branch_city' => 'Ormoc', 'contact' => null],
            ['name' => 'Iloilo Branch', 'branch_code' => 'ILO', 'branch_city' => 'Iloilo', 'contact' => null],
            ['name' => 'Butuan Branch', 'branch_code' => 'BTN', 'branch_city' => 'Butuan', 'contact' => null],
            ['name' => 'CDO Branch', 'branch_code' => 'CDO', 'branch_city' => 'Cagayan de Oro', 'contact' => null],
            ['name' => 'Davao I Branch', 'branch_code' => 'DVO', 'branch_city' => 'Davao', 'contact' => null],
            ['name' => 'Davao II Branch', 'branch_code' => 'DVT', 'branch_city' => 'Davao', 'contact' => null],
            ['name' => 'Gensan Branch', 'branch_code' => 'GNS', 'branch_city' => 'General Santos', 'contact' => null],
            ['name' => 'Ozamiz Branch', 'branch_code' => 'OZM', 'branch_city' => 'Ozamiz', 'contact' => null],
            ['name' => 'Pagadian Branch', 'branch_code' => 'PAG', 'branch_city' => 'Pagadian', 'contact' => null],
        ];
    }

    public function run(): void
    {
        $branchNames = collect($this->branches())->pluck('name')->all();

        Branch::query()
            ->whereNotIn('name', $branchNames)
            ->update(['is_active' => false]);

        foreach ($this->branches() as $branch) {
            Branch::updateOrCreate(
                ['name' => $branch['name']],
                [
                    'branch_code' => $branch['branch_code'],
                    'branch_city' => $branch['branch_city'],
                    'contact' => $branch['contact'],
                    'is_active' => true,
                ],
            );
        }
    }
}
