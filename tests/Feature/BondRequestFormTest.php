<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Services\KycObligeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BondRequestFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_requester_can_create_bond_request_with_form_fields(): void
    {
        $this->mock(KycObligeeService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->with(42)
                ->andReturn([
                    'id' => 42,
                    'company_name' => 'Acme Obligee Corp',
                    'label' => 'Acme Obligee Corp',
                    'business_address' => '123 Rizal Avenue',
                    'business_ctm' => 'Manila',
                    'business_province' => 'Metro Manila',
                ]);
        });

        $requester = $this->requesterUser();
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create(['name' => 'Performance Bond', 'code' => 'PERF']);
        $signatory = Signatory::factory()->create(['name' => 'Jane Signer', 'position' => 'President']);
        $notary = Notary::factory()->create(['name' => 'Atty. Juan Notary']);

        $response = $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_number' => 'BND-2026-001',
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'address_1' => '123 Rizal Avenue',
            'address_2' => 'Manila',
            'address_3' => 'Metro Manila',
            'amount' => 1500.75,
            'project_name' => 'Highway Project',
            'request_date' => '2026-05-24',
            'expiry_date' => '2027-05-24',
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'doc_no' => 'DOC-1',
            'page_no' => '10',
            'book_no' => 'V',
            'series_year' => '2026',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'BND-2026-001',
            'bond_type_id' => $bondType->id,
            'bond_type' => 'PERF',
            'principal_id' => $principal->id,
            'obligee_id' => 42,
            'obligee_name' => 'Acme Obligee Corp',
            'address_1' => '123 Rizal Avenue',
            'address_2' => 'Manila',
            'address_3' => 'Metro Manila',
            'notary_id' => $notary->id,
            'project_name' => 'Highway Project',
            'signatory_id' => $signatory->id,
            'signatory_position' => 'President',
            'doc_no' => 'DOC-1',
            'page_no' => '10',
            'book_no' => 'V',
            'series_year' => '2026',
            'status' => 'pending',
            'created_by' => $requester->id,
        ]);

        $this->assertDatabaseHas('bond_requests', [
            'bond_number' => 'BND-2026-001',
            'amount_in_words' => 'One Thousand Five Hundred Pesos and Seventy Five Centavos Only',
        ]);
    }

    private function requesterUser(): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
