<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use App\Services\AuditLogService;
use App\Services\DepositService;
use App\Services\NotificationService;
use App\Support\BranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DepositController extends Controller
{
    public function __construct(
        private DepositService $depositService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyPermission(['deposits.view', 'deposits.create']), 403);

        $user = $request->user();
        $user->loadMissing('branch');
        $canViewAll = $user->hasPermission('deposits.view');
        $branchId = $request->integer('branch_id') ?: null;

        // Admins can pass ?mine=1 to see only their own submissions
        $mineOnly = ! $canViewAll || $request->boolean('mine');

        $deposits = Deposit::with(['user:id,name', 'bankAccount', 'approver:id,name'])
            ->when($mineOnly, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $mineOnly, fn ($q) => BranchScope::applyUserRelationScope($q, $user, $branchId))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('search')->trim()->toString(), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('reference_number', 'like', '%'.$search.'%')
                        ->orWhereHas('bankAccount', fn ($q) => $q->where('bank_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Deposits/Index', [
            'deposits' => $deposits,
            'isAdmin' => $canViewAll && ! $mineOnly,
            'canSubmit' => $user->hasPermission('deposits.create'),
            'filters' => $request->only('status', 'mine', 'search', 'branch_id'),
            'statusOptions' => DepositStatus::options(),
            'userBalance' => $user->branchBalance(),
            'branchName' => $user->branch?->name,
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user) && $canViewAll && ! $mineOnly,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('deposits.create'), 403);

        $user = $request->user();
        $user->loadMissing('branch');

        return Inertia::render('Deposits/Create', [
            'bankAccounts' => BankAccount::where('is_active', true)->get(['id', 'bank_name', 'account_number', 'account_name', 'branch']),
            'userBalance' => $user->branchBalance(),
            'branchName' => $user->branch?->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deposits.create'), 403);

        $validated = $request->validate([
            'bank_account_id' => [
                'required',
                Rule::exists('bank_accounts', 'id')->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference_number' => ['required', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'deposit_date' => ['required', 'date'],
        ]);

        $path = $request->file('receipt')->store('receipts', 'public');

        $deposit = Deposit::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'receipt_path' => $path,
            'status' => DepositStatus::Pending,
        ]);

        ActivityLogger::log('deposit.submitted', 'Deposit request submitted for ₱'.number_format($validated['amount'], 2));
        AuditLogService::log(
            user: $request->user(),
            action: 'receipt_uploaded',
            entityType: AuditLogService::ENTITY_RECEIPT,
            entityId: $deposit->id,
            newValues: [
                'amount' => (string) $deposit->amount,
                'status' => $deposit->status->value,
            ],
            description: "Receipt uploaded for deposit #{$deposit->id}.",
        );
        $this->notificationService->depositSubmitted($deposit);

        return redirect()->route('payments.deposits.index')
            ->with('success', 'Deposit request submitted. Please wait for admin approval.');
    }

    public function show(Request $request, Deposit $deposit): Response
    {
        abort_unless(
            $request->user()->hasPermission('deposits.view') || $deposit->user_id === $request->user()->id,
            403,
        );

        $deposit->load(['user:id,name,email,branch_id', 'user.branch:id,name,balance', 'bankAccount', 'approver:id,name']);

        AuditLogService::log(
            user: $request->user(),
            action: 'receipt_viewed',
            entityType: AuditLogService::ENTITY_RECEIPT,
            entityId: $deposit->id,
            description: "Receipt viewed for deposit #{$deposit->id}.",
        );

        $transaction = Transaction::where('subject_type', Deposit::class)
            ->where('subject_id', $deposit->id)
            ->first();

        return Inertia::render('Deposits/Show', [
            'deposit' => $deposit,
            'receiptUrl' => Storage::disk('public')->url($deposit->receipt_path),
            'receiptDownloadUrl' => route('payments.deposits.download-receipt', $deposit),
            'canApprove' => $request->user()->hasPermission('deposits.approve') && $deposit->status === DepositStatus::Pending,
            'submitterBalance' => (float) ($deposit->user->branch?->balance ?? 0),
            'branchName' => $deposit->user->branch?->name,
            'transactionNumber' => $transaction?->transaction_number,
        ]);
    }

    public function downloadReceipt(Request $request, Deposit $deposit): BinaryFileResponse
    {
        abort_unless(
            $request->user()->hasPermission('deposits.view') || $deposit->user_id === $request->user()->id,
            403,
        );

        abort_unless(
            Storage::disk('public')->exists($deposit->receipt_path),
            404,
            'Receipt file not found.',
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'receipt_downloaded',
            entityType: AuditLogService::ENTITY_RECEIPT,
            entityId: $deposit->id,
            description: "Receipt downloaded for deposit #{$deposit->id}.",
        );

        $extension = pathinfo($deposit->receipt_path, PATHINFO_EXTENSION) ?: 'bin';

        return response()->download(
            Storage::disk('public')->path($deposit->receipt_path),
            sprintf('deposit-%d-receipt.%s', $deposit->id, $extension),
        );
    }

    public function approve(Request $request, Deposit $deposit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deposits.approve'), 403);
        abort_unless($deposit->status === DepositStatus::Pending, 422, 'Only pending deposits can be approved.');

        $transaction = $this->depositService->approve($deposit, $request->user())->load('branch');
        $creditedUser = $transaction->user;
        $branchName = $transaction->branch?->name ?? 'Branch';

        ActivityLogger::log('deposit.approved', "Deposit #{$deposit->id} approved for {$creditedUser->name}.", $deposit);
        AuditLogService::log(
            user: $request->user(),
            action: 'receipt_approved',
            entityType: AuditLogService::ENTITY_RECEIPT,
            entityId: $deposit->id,
            oldValues: ['status' => DepositStatus::Pending->value],
            newValues: ['status' => DepositStatus::Approved->value],
            description: "Receipt approved for deposit #{$deposit->id}.",
        );
        $this->notificationService->depositApproved($deposit);

        return back()->with(
            'success',
            "Deposit approved. {$branchName} fund is now ₱".number_format((float) $transaction->balance_after, 2)
            .". Transaction number: {$transaction->transaction_number}",
        );
    }

    public function reject(Request $request, Deposit $deposit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deposits.approve'), 403);
        abort_unless($deposit->status === DepositStatus::Pending, 422, 'Only pending deposits can be rejected.');

        $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        $this->depositService->reject($deposit, $request->user(), $request->input('remarks'));

        ActivityLogger::log('deposit.rejected', "Deposit #{$deposit->id} rejected.", $deposit);
        AuditLogService::log(
            user: $request->user(),
            action: 'receipt_rejected',
            entityType: AuditLogService::ENTITY_RECEIPT,
            entityId: $deposit->id,
            oldValues: ['status' => DepositStatus::Pending->value],
            newValues: ['status' => DepositStatus::Rejected->value],
            description: "Receipt rejected for deposit #{$deposit->id}.",
        );
        $this->notificationService->depositRejected($deposit);

        return back()->with('success', 'Deposit rejected.');
    }
}
