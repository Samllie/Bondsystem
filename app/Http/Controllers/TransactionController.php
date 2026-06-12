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

        $transactions = Transaction::with('user:id,name')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($isAdmin, fn ($q) => BranchScope::applyUserRelationScope($q, $user, $branchId))
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
            'filters' => $request->only('type', 'search', 'branch_id'),
            'userBalance' => ! $isAdmin ? $user->balance : null,
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user),
        ]);
    }
}
