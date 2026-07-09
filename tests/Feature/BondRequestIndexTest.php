<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BondRequestIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_approver_can_access_create_form(): void
    {
        $approver = $this->userWithRole(RoleSlug::Approver);

        $this->actingAs($approver)
            ->get(route('bond-requests.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('BondRequests/Form'));
    }

    public function test_index_includes_requester_and_approver_names(): void
    {
        $requester = $this->userWithRole(RoleSlug::Requester);
        $approver = $this->userWithRole(RoleSlug::Approver);
        $signatory = Signatory::factory()->create(['is_active' => true]);

        $pendingBond = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        $approvedBond = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'approved_by' => $approver->id,
            'signatory_id' => $signatory->id,
            'docx_path' => 'private/generated-docx/2026/07/request_test.docx',
        ]);

        $response = $this->actingAs($approver)->get(route('bond-requests.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BondRequests/Index')
            ->has('bondRequests.data', 2)
            ->where('bondRequests.data', function ($records) use ($requester, $approver, $pendingBond, $approvedBond) {
                $pending = collect($records)->firstWhere('id', $pendingBond->id);
                $approved = collect($records)->firstWhere('id', $approvedBond->id);

                return $pending['creator']['name'] === $requester->name
                    && $pending['approver'] === null
                    && $pending['has_docx'] === false
                    && $approved['creator']['name'] === $requester->name
                    && $approved['approver']['name'] === $approver->name
                    && $approved['has_docx'] === true;
            })
        );
    }

    public function test_encoder_can_download_docx_from_bond_request_index(): void
    {
        $branch = $this->makeBranch('ENC');
        $encoder = $this->userWithRole(RoleSlug::Encoder, $branch);
        $requester = $this->userWithRole(RoleSlug::Requester, $branch);

        $bondRequest = BondRequest::factory()->approved()->create([
            'created_by' => $requester->id,
            'docx_path' => 'private/generated-docx/2026/07/request_encoder.docx',
        ]);

        $absolutePath = storage_path('app/'.$bondRequest->docx_path);
        File::ensureDirectoryExists(dirname($absolutePath));
        file_put_contents($absolutePath, 'docx test');

        $this->actingAs($encoder)
            ->get(route('bond-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bondRequests.data', function ($records) use ($bondRequest) {
                    $record = collect($records)->firstWhere('id', $bondRequest->id);

                    return $record !== null && $record['has_docx'] === true;
                })
            );

        $this->actingAs($encoder)
            ->get(route('bond-requests.download-docx', $bondRequest))
            ->assertOk();
    }

    private function makeBranch(string $code): Branch
    {
        return Branch::query()->create([
            'name' => "{$code} Branch",
            'branch_code' => $code,
            'branch_city' => 'Test City',
            'is_active' => true,
        ]);
    }

    private function userWithRole(RoleSlug $slug, ?Branch $branch = null): User
    {
        $role = Role::where('slug', $slug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'branch_id' => $branch?->id,
            'branch_code' => $branch?->branch_code,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
