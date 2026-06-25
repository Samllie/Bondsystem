<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\Transaction;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('transactions.view'), 403);

        $user = $request->user();
        $isAdmin = ! $user->hasRole(RoleSlug::Requester);
        $branchId = $request->integer('branch_id') ?: null;

        $transactions = Transaction::with([
            'user:id,name',
            'branch:id,name',
            'bondRequest',
        ])
            ->when(! $isAdmin, fn ($q) => BranchScope::applyTransactionScope($q, $user, null))
            ->when($isAdmin, fn ($q) => BranchScope::applyTransactionScope($q, $user, $branchId))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->string('search')->trim()->toString(), function ($q, $search) {
                $q->where('transaction_number', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'isAdmin' => $isAdmin,
            'canReturnFund' => $user->hasRole(RoleSlug::SuperAdmin),
            'filters' => $request->only('type', 'search', 'branch_id'),
            'userBalance' => ! $isAdmin ? $user->branchBalance() : null,
            'branchName' => ! $isAdmin ? $user->branch?->name : null,
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user),
        ]);
    }
}
