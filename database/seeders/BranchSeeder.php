<?php

namespace Database\Seeders;

use App\Models\Maintenance\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, branch_code: string, address: string|null, contact: string|null}>
     */
    private function branches(): array
    {
        return [
            ['name' => 'Main Office- Makati', 'branch_code' => 'MAK', 'address' => 'Makati', 'contact' => null],
            ['name' => 'Alabang Branch', 'branch_code' => 'ALB', 'address' => 'Muntinlupa', 'contact' => null],
            ['name' => 'Manila I Branch', 'branch_code' => 'MNL', 'address' => 'Manila', 'contact' => null],
            ['name' => 'Manila II Branch', 'branch_code' => 'MNA', 'address' => 'Manila', 'contact' => null],
            ['name' => 'West Avenue Branch', 'branch_code' => 'WST', 'address' => 'Quezon', 'contact' => null],
            ['name' => 'Cubao Branch', 'branch_code' => 'CUB', 'address' => 'Quezon', 'contact' => null],
            ['name' => 'Angeles Branch', 'branch_code' => 'ANG', 'address' => 'Angeles', 'contact' => null],
            ['name' => 'Batangas Branch', 'branch_code' => 'BTG', 'address' => 'Lipa', 'contact' => null],
            ['name' => 'Cabanatuan Branch', 'branch_code' => 'CBN', 'address' => 'Cabanatuan', 'contact' => null],
            ['name' => 'Dagupan Branch', 'branch_code' => 'DGP', 'address' => 'Dagupan', 'contact' => null],
            ['name' => 'Isabela Branch', 'branch_code' => 'ISB', 'address' => 'Santiago', 'contact' => null],
            ['name' => 'La Union Branch', 'branch_code' => 'LAU', 'address' => 'San Fernando', 'contact' => null],
            ['name' => 'Laoag Branch', 'branch_code' => 'LAO', 'address' => 'Laoag', 'contact' => null],
            ['name' => 'Legazpi I Branch', 'branch_code' => 'LGP', 'address' => 'Legazpi', 'contact' => null],
            ['name' => 'Legazpi II Branch', 'branch_code' => 'LGZ', 'address' => 'Legazpi', 'contact' => null],
            ['name' => 'Mindoro Branch', 'branch_code' => 'MIN', 'address' => 'Calapan', 'contact' => null],
            ['name' => 'Naga Branch', 'branch_code' => 'NGA', 'address' => 'Naga', 'contact' => null],
            ['name' => 'San Fernando Branch', 'branch_code' => 'SNF', 'address' => 'San Fernando', 'contact' => null],
            ['name' => 'Tuguegarao Branch', 'branch_code' => 'TUG', 'address' => 'Tuguegarao', 'contact' => null],
            ['name' => 'Vigan Branch', 'branch_code' => 'VIG', 'address' => 'Vigan', 'contact' => null],
            ['name' => 'Bacolod Branch', 'branch_code' => 'BCD', 'address' => 'Bacolod', 'contact' => null],
            ['name' => 'Cebu Branch', 'branch_code' => 'CEB', 'address' => 'Cebu', 'contact' => null],
            ['name' => 'Tacloban Branch', 'branch_code' => 'TAC', 'address' => 'Tacloban', 'contact' => null],
            ['name' => 'Ormoc Branch', 'branch_code' => 'ORM', 'address' => 'Ormoc', 'contact' => null],
            ['name' => 'Iloilo Branch', 'branch_code' => 'ILO', 'address' => 'Iloilo', 'contact' => null],
            ['name' => 'Butuan Branch', 'branch_code' => 'BTN', 'address' => 'Butuan', 'contact' => null],
            ['name' => 'CDO Branch', 'branch_code' => 'CDO', 'address' => 'Cagayan de Oro', 'contact' => null],
            ['name' => 'Davao I Branch', 'branch_code' => 'DVO', 'address' => 'Davao', 'contact' => null],
            ['name' => 'Davao II Branch', 'branch_code' => 'DVT', 'address' => 'Davao', 'contact' => null],
            ['name' => 'Gensan Branch', 'branch_code' => 'GNS', 'address' => 'General Santos', 'contact' => null],
            ['name' => 'Ozamiz Branch', 'branch_code' => 'OZM', 'address' => 'Ozamiz', 'contact' => null],
            ['name' => 'Pagadian Branch', 'branch_code' => 'PAG', 'address' => 'Pagadian', 'contact' => null],
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
                    'address' => $branch['address'],
                    'contact' => $branch['contact'],
                    'is_active' => true,
                ],
            );
        }
    }
}
