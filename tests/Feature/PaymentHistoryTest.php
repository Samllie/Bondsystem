<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\PaymentHistory;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificateGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_bond_request_creation_does_not_create_payment_history(): void
    {
        $requester = $this->requesterUser(balance: 5000);
        $principal = Principal::factory()->create();
        $bondType = BondTypeMaster::factory()->create(['code' => 'G(42)', 'bond_serial' => '0008384']);

        $this->actingAs($requester)->post(route('bond-requests.store'), [
            'bond_type_id' => $bondType->id,
            'principal_id' => $principal->id,
            'principal_name' => $principal->company_name,
            'obligee_name' => 'Typed Obligee Corp',
            'amount' => 1500.75,
            'request_date' => '2026-05-24',
            'inception_date' => '2026-05-01',
            'certificate_type' => CertificateType::BondCertificate->value,
            'party_type' => 'private',
            'expiry_date' => '2027-05-24',
        ])->assertRedirect();

        $this->assertDatabaseCount('payment_histories', 0);
    }

    public function test_document_fee_creates_payment_history_when_certificate_is_generated(): void
    {
        $approver = $this->approverUser();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'minimum_balance' => 1000,
            'balance' => 10000,
            'is_active' => true,
        ]);
        $requester = User::factory()->create(['branch_id' => $branch->id]);
        $signatory = Signatory::factory()->create(['is_active' => true]);
        $notary = Notary::factory()->create(['is_active' => true]);
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate->value,
            'bond_number' => 'G(42)',
            'signatory_id' => $signatory->id,
            'notary_id' => $notary->id,
            'created_by' => $requester->id,
        ]);

        $this->mock(CertificateGenerationService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once();
        });

        $this->actingAs($approver)
            ->post(route('bond-requests.generate-certificate', $bondRequest), [
                'signatory_id' => $signatory->id,
                'notary_id' => $notary->id,
                'doc_no' => '1',
                'page_no' => '1',
                'book_no' => 'I',
                'series_year' => '2026',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $bondRequest->refresh();

        $this->assertDatabaseHas('payment_histories', [
            'bond_request_id' => $bondRequest->id,
            'user_id' => $requester->id,
            'amount' => 500,
            'description' => "Document fee — {$bondRequest->bond_number}",
        ]);
    }

    public function test_payment_history_index_only_shows_document_fee_payments(): void
    {
        $requester = $this->requesterUser(balance: 5000);
        $legacyBondRequest = BondRequest::factory()->create(['created_by' => $requester->id]);
        $documentBondRequest = BondRequest::factory()->create(['created_by' => $requester->id]);

        PaymentHistory::query()->create([
            'user_id' => $requester->id,
            'bond_request_id' => $legacyBondRequest->id,
            'amount' => 99999,
            'description' => 'Bond request payment — G(42)',
            'paid_at' => now(),
        ]);

        PaymentHistory::query()->create([
            'user_id' => $requester->id,
            'bond_request_id' => $documentBondRequest->id,
            'amount' => 500,
            'description' => 'Document fee — G(42)',
            'paid_at' => now(),
        ]);

        $this->actingAs($requester)
            ->get(route('payments.histories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PaymentHistories/Index')
                ->has('payments.data', 1)
                ->where('payments.data.0.amount', '500.00')
            );
    }

    private function requesterUser(float $balance = 10000): User
    {
        $role = Role::where('slug', RoleSlug::Requester->value)->firstOrFail();
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'branch_city' => 'Makati',
            'notary_price' => 500,
            'minimum_balance' => 1000,
            'balance' => $balance,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'branch_code' => 'MKT',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function approverUser(): User
    {
        $role = Role::where('slug', RoleSlug::Approver->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
