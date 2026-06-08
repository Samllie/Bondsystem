<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Models\BankAccount;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use App\Services\DepositService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
        $canViewAll = $user->hasPermission('deposits.view');

        // Admins can pass ?mine=1 to see only their own submissions
        $mineOnly = ! $canViewAll || $request->boolean('mine');

        $deposits = Deposit::with(['user:id,name', 'bankAccount', 'approver:id,name'])
            ->when($mineOnly, fn ($q) => $q->where('user_id', $user->id))
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
            'filters' => $request->only('status', 'mine', 'search'),
            'statusOptions' => DepositStatus::options(),
            'userBalance' => $user->balance,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('deposits.create'), 403);

        return Inertia::render('Deposits/Create', [
            'bankAccounts' => BankAccount::where('is_active', true)->get(['id', 'bank_name', 'account_number', 'account_name', 'branch']),
            'userBalance' => $request->user()->balance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deposits.create'), 403);

        $validated = $request->validate([
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
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

        $deposit->load(['user:id,name,email,balance', 'bankAccount', 'approver:id,name']);

        $transaction = Transaction::where('subject_type', Deposit::class)
            ->where('subject_id', $deposit->id)
            ->first();

        return Inertia::render('Deposits/Show', [
            'deposit' => $deposit,
            'receiptUrl' => Storage::disk('public')->url($deposit->receipt_path),
            'canApprove' => $request->user()->hasPermission('deposits.approve') && $deposit->status === DepositStatus::Pending,
            'submitterBalance' => (float) $deposit->user->balance,
            'transactionNumber' => $transaction?->transaction_number,
        ]);
    }

    public function approve(Request $request, Deposit $deposit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('deposits.approve'), 403);
        abort_unless($deposit->status === DepositStatus::Pending, 422, 'Only pending deposits can be approved.');

        $transaction = $this->depositService->approve($deposit, $request->user());
        $creditedUser = $transaction->user;

        ActivityLogger::log('deposit.approved', "Deposit #{$deposit->id} approved for {$creditedUser->name}.", $deposit);
        $this->notificationService->depositApproved($deposit);

        return back()->with(
            'success',
            "Deposit approved. {$creditedUser->name}'s balance is now ₱".number_format((float) $creditedUser->balance, 2)
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
        $this->notificationService->depositRejected($deposit);

        return back()->with('success', 'Deposit rejected.');
    }
}
