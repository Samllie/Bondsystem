<?php

namespace Tests\Feature;

use App\Models\Maintenance\Branch;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_seeder_populates_sterling_branches(): void
    {
        $this->seed(BranchSeeder::class);

        $this->assertDatabaseHas('branches', ['name' => 'Main Office- Makati', 'address' => 'Makati', 'is_active' => true]);
        $this->assertDatabaseHas('branches', ['name' => 'Cebu Branch', 'address' => 'Cebu', 'is_active' => true]);
        $this->assertDatabaseHas('branches', ['name' => 'CDO Branch', 'address' => 'Cagayan de Oro', 'is_active' => true]);

        $this->assertSame(32, Branch::where('is_active', true)->count());
    }

    public function test_branch_seeder_is_idempotent(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(BranchSeeder::class);

        $this->assertSame(32, Branch::where('is_active', true)->count());
    }
}
