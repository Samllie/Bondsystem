<?php

namespace App\Http\Controllers\Maintenance;

use App\Enums\RoleSlug;
use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends MaintenanceController
{
    protected function modelClass(): string
    {
        return BankAccount::class;
    }

    protected function page(): string
    {
        return 'Maintenance/BankAccounts/Form';
    }

    protected function routePrefix(): string
    {
        return 'maintenance.bank-accounts';
    }

    protected function label(): string
    {
        return 'Bank Account';
    }

    protected function rules(bool $isUpdate = false, ?Model $record = null): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('bank_accounts', 'account_number')->ignore($record?->id),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        $records = BankAccount::query()
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('bank_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('account_name', 'like', "%{$search}%")
                        ->orWhere('branch', 'like', "%{$search}%");
                });
            })
            ->orderBy('bank_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Maintenance/BankAccounts/Index', [
            'records' => $records,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        return Inertia::render($this->page(), [
            'bankAccount' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        $validated = $request->validate($this->rules());

        $bankAccount = BankAccount::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLogService::log(
            user: $request->user(),
            action: 'bank_account_created',
            entityType: AuditLogService::ENTITY_BANK_ACCOUNT,
            entityId: $bankAccount->id,
            newValues: $bankAccount->only(['bank_name', 'account_number', 'account_name', 'branch', 'is_active']),
            description: "Bank account {$bankAccount->bank_name} ({$bankAccount->account_number}) created.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} created successfully.");
    }

    public function edit(Request $request, int $id): Response
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        return Inertia::render($this->page(), [
            'bankAccount' => BankAccount::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        $bankAccount = BankAccount::findOrFail($id);
        $oldValues = $bankAccount->only(['bank_name', 'account_number', 'account_name', 'branch', 'is_active']);

        $validated = $request->validate($this->rules(true, $bankAccount));

        $bankAccount->update([
            ...$validated,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        AuditLogService::log(
            user: $request->user(),
            action: 'bank_account_updated',
            entityType: AuditLogService::ENTITY_BANK_ACCOUNT,
            entityId: $bankAccount->id,
            oldValues: $oldValues,
            newValues: $bankAccount->only(['bank_name', 'account_number', 'account_name', 'branch', 'is_active']),
            description: "Bank account {$bankAccount->bank_name} ({$bankAccount->account_number}) updated.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} updated successfully.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasRole(RoleSlug::SuperAdmin), 403);

        $bankAccount = BankAccount::findOrFail($id);

        if ($bankAccount->deposits()->exists()) {
            return back()->with('error', 'This bank account has deposit records and cannot be deleted. Deactivate it instead.');
        }

        $oldValues = $bankAccount->only(['bank_name', 'account_number', 'account_name', 'branch', 'is_active']);

        $bankAccount->delete();

        AuditLogService::log(
            user: $request->user(),
            action: 'bank_account_deleted',
            entityType: AuditLogService::ENTITY_BANK_ACCOUNT,
            entityId: $bankAccount->id,
            oldValues: $oldValues,
            description: "Bank account {$oldValues['bank_name']} ({$oldValues['account_number']}) deleted.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} deleted.");
    }
}
