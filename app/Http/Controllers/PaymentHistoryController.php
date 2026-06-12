<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\PaymentHistory;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('payment-histories.view'), 403);

        $user = $request->user();
        $isAdmin = ! $user->hasRole(RoleSlug::Requester);
        $branchId = $request->integer('branch_id') ?: null;

        $payments = PaymentHistory::with([
            'user:id,name',
            'bondRequest:id,bond_number,bond_type,status,principal_id,obligee_id,obligee_name',
            'bondRequest.principal:id,company_name',
        ])
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($isAdmin, fn ($q) => BranchScope::applyUserRelationScope($q, $user, $branchId))
            ->when($request->string('search')->trim()->toString(), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('bondRequest', fn ($q) => $q->where('bond_number', 'like', '%'.$search.'%'));
                });
            })
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('PaymentHistories/Index', [
            'payments' => $payments,
            'isAdmin' => $isAdmin,
            'filters' => $request->only('search', 'branch_id'),
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user),
        ]);
    }
}
