<?php

namespace App\Http\Controllers;

use App\Enums\BondRequestStatus;
use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\ActivityLog;
use App\Models\BondRequest;
use App\Models\Deposit;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return $user->hasRole(RoleSlug::Requester)
            ? $this->requesterDashboard($user)
            : $this->adminDashboard($user);
    }

    private function requesterDashboard(User $user): Response
    {
        $bondQuery = BondRequest::where('created_by', $user->id);

        $stats = [
            'my_bonds' => (clone $bondQuery)->count(),
            'pending' => (clone $bondQuery)->where('status', BondRequestStatus::Pending)->count(),
            'approved' => (clone $bondQuery)->where('status', BondRequestStatus::Approved)->count(),
            'notarized' => (clone $bondQuery)->where('status', BondRequestStatus::Notarized)->count(),
            'balance' => $user->balance,
            'pending_deposits' => Deposit::where('user_id', $user->id)->where('status', DepositStatus::Pending)->count(),
        ];

        $recentRequests = (clone $bondQuery)
            ->with(['principal:id,company_name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (BondRequest $b) => [
                'id' => $b->id,
                'bond_number' => $b->bond_number,
                'bond_type' => $b->bond_type->label(),
                'principal' => $b->principal?->company_name,
                'amount' => $b->amount,
                'status' => $b->status->value,
                'status_label' => $b->status->label(),
                'status_color' => $b->status->color(),
                'request_date' => $b->request_date->format('M d, Y'),
            ]);

        $recentTransactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'description' => $t->description,
                'created_at' => $t->created_at->format('M d, Y'),
            ]);

        $chartData = (clone $bondQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count, $status) => [
                'status' => BondRequestStatus::from($status)->label(),
                'count' => (int) $count,
                'color' => $this->statusColor($status),
            ])->values();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
            'isRequester' => true,
        ]);
    }

    private function adminDashboard(User $user): Response
    {
        $bondQuery = BondRequest::query();

        $stats = [
            'total_bonds' => (clone $bondQuery)->count(),
            'pending_approval' => (clone $bondQuery)->where('status', BondRequestStatus::Pending)->count(),
            'approved' => (clone $bondQuery)->where('status', BondRequestStatus::Approved)->count(),
            'notarized' => (clone $bondQuery)->where('status', BondRequestStatus::Notarized)->count(),
            'total_users' => User::where('is_active', true)->count(),
            'pending_deposits' => Deposit::where('status', DepositStatus::Pending)->count(),
            'total_obligees' => Obligee::count(),
            'total_principals' => Principal::count(),
        ];

        $recentRequests = (clone $bondQuery)
            ->with(['principal:id,company_name', 'creator:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (BondRequest $b) => [
                'id' => $b->id,
                'bond_number' => $b->bond_number,
                'bond_type' => $b->bond_type->label(),
                'principal' => $b->principal?->company_name,
                'obligee' => $b->obligee_name,
                'amount' => $b->amount,
                'status' => $b->status->value,
                'status_label' => $b->status->label(),
                'status_color' => $b->status->color(),
                'request_date' => $b->request_date->format('M d, Y'),
            ]);

        $pendingDeposits = Deposit::with(['user:id,name', 'bankAccount:id,bank_name,account_number'])
            ->where('status', DepositStatus::Pending)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Deposit $d) => [
                'id' => $d->id,
                'user' => $d->user?->name,
                'bank' => $d->bankAccount?->bank_name,
                'amount' => $d->amount,
                'reference_number' => $d->reference_number,
                'deposit_date' => $d->deposit_date->format('M d, Y'),
            ]);

        $chartData = (clone $bondQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count, $status) => [
                'status' => BondRequestStatus::from($status)->label(),
                'count' => (int) $count,
                'color' => $this->statusColor($status),
            ])->values();

        $monthlyData = (clone $bondQuery)
            ->selectRaw('DATE_FORMAT(request_date, "%Y-%m") as month, COUNT(*) as count')
            ->where('request_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['month' => $row->month, 'count' => (int) $row->count]);

        $activityFeed = ActivityLog::with('user:id,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'user' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->diffForHumans(),
            ]);

        return Inertia::render('AdminDashboard', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'pendingDeposits' => $pendingDeposits,
            'chartData' => $chartData,
            'monthlyData' => $monthlyData,
            'activityFeed' => $activityFeed,
            'isRequester' => false,
        ]);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#D99E1A',
            'approved' => '#22804a',
            'notarized' => '#1A6333',
            'rejected' => '#dc2626',
            'draft' => '#94a3b8',
            default => '#64748b',
        };
    }
}
