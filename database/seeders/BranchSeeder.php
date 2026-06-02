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
            ['name' => 'Main Office- Makati', 'address' => 'Makati', 'contact' => null],
            ['name' => 'Alabang Branch', 'address' => 'Muntinlupa', 'contact' => null],
            ['name' => 'Manila I Branch', 'address' => 'Manila', 'contact' => null],
            ['name' => 'Manila II Branch', 'address' => 'Manila', 'contact' => null],
            ['name' => 'West Avenue Branch', 'address' => 'Quezon', 'contact' => null],
            ['name' => 'Cubao Branch', 'address' => 'Quezon', 'contact' => null],
            ['name' => 'Angeles Branch', 'address' => 'Angeles', 'contact' => null],
            ['name' => 'Batangas Branch', 'address' => 'Lipa', 'contact' => null],
            ['name' => 'Cabanatuan Branch', 'address' => 'Cabanatuan', 'contact' => null],
            ['name' => 'Dagupan Branch', 'address' => 'Dagupan', 'contact' => null],
            ['name' => 'Isabela Branch', 'address' => 'Santiago', 'contact' => null],
            ['name' => 'La Union Branch', 'address' => 'San Fernando', 'contact' => null],
            ['name' => 'Laoag Branch', 'address' => 'Laoag', 'contact' => null],
            ['name' => 'Legazpi I Branch', 'address' => 'Legazpi', 'contact' => null],
            ['name' => 'Legazpi II Branch', 'address' => 'Legazpi', 'contact' => null],
            ['name' => 'Mindoro Branch', 'address' => 'Calapan', 'contact' => null],
            ['name' => 'Naga Branch', 'address' => 'Naga', 'contact' => null],
            ['name' => 'San Fernando Branch', 'address' => 'San Fernando', 'contact' => null],
            ['name' => 'Tuguegarao Branch', 'address' => 'Tuguegarao', 'contact' => null],
            ['name' => 'Vigan Branch', 'address' => 'Vigan', 'contact' => null],
            ['name' => 'Bacolod Branch', 'address' => 'Bacolod', 'contact' => null],
            ['name' => 'Cebu Branch', 'address' => 'Cebu', 'contact' => null],
            ['name' => 'Tacloban Branch', 'address' => 'Tacloban', 'contact' => null],
            ['name' => 'Ormoc Branch', 'address' => 'Ormoc', 'contact' => null],
            ['name' => 'Iloilo Branch', 'address' => 'Iloilo', 'contact' => null],
            ['name' => 'Butuan Branch', 'address' => 'Butuan', 'contact' => null],
            ['name' => 'CDO Branch', 'address' => 'Cagayan de Oro', 'contact' => null],
            ['name' => 'Davao I Branch', 'address' => 'Davao', 'contact' => null],
            ['name' => 'Davao II Branch', 'address' => 'Davao', 'contact' => null],
            ['name' => 'Gensan Branch', 'address' => 'General Santos', 'contact' => null],
            ['name' => 'Ozamiz Branch', 'address' => 'Ozamiz', 'contact' => null],
            ['name' => 'Pagadian Branch', 'address' => 'Pagadian', 'contact' => null],
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
                    'address' => $branch['address'],
                    'contact' => $branch['contact'],
                    'is_active' => true,
                ],
            );
        }
    }
}
