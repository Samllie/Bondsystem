<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\BankAccount;
use App\Models\BondRequest;
use App\Models\Deposit;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_bond_request_submission_notifies_approvers(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, ['balance' => 1000]);
        $approver = $this->createUser(RoleSlug::Approver);
        $otherRequester = $this->createUser(RoleSlug::Requester);

        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
            'bond_number' => 'G(42)',
        ]);

        app(NotificationService::class)->bondRequestSubmitted($bondRequest);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $approver->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $requester->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $otherRequester->id,
        ]);

        $notification = $approver->fresh()->notifications->first();
        $this->assertSame('bond_request.submitted', $notification->data['type']);
        $this->assertSame(route('bond-requests.show', $bondRequest), $notification->data['url']);
    }

    public function test_bond_request_approval_notifies_requester(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, ['balance' => 1000]);
        $branch = Branch::query()->create([
            'name' => 'MKT Branch',
            'branch_code' => 'MKT',
            'address' => 'Branch City',
            'notary_price' => 500,
            'is_active' => true,
        ]);
        $requester->update([
            'branch_id' => $branch->id,
            'branch_code' => 'MKT',
            'branch_city' => 'Branch City',
        ]);

        $approver = $this->createUser(RoleSlug::Approver);
        $signatory = Signatory::factory()->create();

        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
            'bond_number' => 'G(42)',
            'certificate_type' => CertificateType::CarCertificate->value,
        ]);

        $this->actingAs($approver)->post(route('bond-requests.approve', $bondRequest), [
            'signatory_id' => $signatory->id,
            'doc_no' => '1',
            'page_no' => '1',
            'book_no' => '1',
            'series_year' => '2026',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $requester->id,
        ]);

        $notification = $requester->fresh()->notifications->first();
        $this->assertSame('bond_request.approved', $notification->data['type']);
    }

    public function test_bond_request_rejection_notifies_requester(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $approver = $this->createUser(RoleSlug::Approver);

        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        $this->actingAs($approver)->post(route('bond-requests.reject', $bondRequest), [
            'remarks' => 'Incomplete documents',
        ])->assertRedirect();

        $notification = $requester->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('bond_request.rejected', $notification->data['type']);
    }

    public function test_deposit_submission_notifies_approvers(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $this->actingAs($requester)->post(route('payments.deposits.store'), [
            'bank_account_id' => $bankAccount->id,
            'amount' => 2500,
            'reference_number' => 'REF-12345678',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            'deposit_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $approver->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $requester->id,
        ]);

        $notification = $approver->fresh()->notifications->first();
        $this->assertSame('deposit.submitted', $notification->data['type']);
    }

    public function test_deposit_approval_notifies_requester(): void
    {
        $requester = $this->createUser(RoleSlug::Requester, ['balance' => 0]);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 1500,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)->post(route('payments.deposits.approve', $deposit))->assertRedirect();

        $notification = $requester->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('deposit.approved', $notification->data['type']);
    }

    public function test_deposit_rejection_notifies_requester(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $approver = $this->createUser(RoleSlug::Approver);
        $bankAccount = BankAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'user_id' => $requester->id,
            'bank_account_id' => $bankAccount->id,
            'status' => DepositStatus::Pending,
        ]);

        $this->actingAs($approver)->post(route('payments.deposits.reject', $deposit), [
            'remarks' => 'Invalid receipt',
        ])->assertRedirect();

        $notification = $requester->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('deposit.rejected', $notification->data['type']);
    }

    public function test_mark_notification_as_read_redirects_to_target_url(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        app(NotificationService::class)->bondRequestApproved($bondRequest);

        $notification = $requester->fresh()->notifications->first();
        $this->assertNotNull($notification);

        $this->actingAs($requester)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('bond-requests.show', $bondRequest));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifications_index_page_loads_for_authenticated_user(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        app(NotificationService::class)->bondRequestApproved($bondRequest);

        $this->actingAs($requester)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
            );
    }

    public function test_shared_inertia_props_include_unread_count(): void
    {
        $requester = $this->createUser(RoleSlug::Requester);
        $bondRequest = BondRequest::factory()->pending()->create([
            'created_by' => $requester->id,
        ]);

        app(NotificationService::class)->bondRequestApproved($bondRequest);

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread_count', 1)
                ->has('notifications.recent', 1)
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(RoleSlug $roleSlug, array $attributes = []): User
    {
        $role = Role::where('slug', $roleSlug->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }
}
