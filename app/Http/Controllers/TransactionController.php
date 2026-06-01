<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('transactions.view'), 403);

        $isAdmin = ! $request->user()->hasRole(RoleSlug::Requester);

        $transactions = Transaction::with('user:id,name')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $request->user()->id))
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
            'filters' => $request->only('type', 'search'),
            'userBalance' => ! $isAdmin ? $request->user()->balance : null,
        ]);
    }
}
