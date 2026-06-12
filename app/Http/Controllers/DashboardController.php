<?php

namespace App\Http\Controllers;

use App\Enums\BondRequestStatus;
use App\Enums\DepositStatus;
use App\Enums\RoleSlug;
use App\Models\ActivityLog;
use App\Models\BondRequest;
use App\Models\Deposit;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Branch;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->resolveFilters($request, $user);
        $shared = [
            'filters' => $filters,
            'statusOptions' => BondRequestStatus::options(),
            'bondTypeOptions' => $this->bondTypeOptions(),
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user),
            'filterSummary' => $this->filterSummary($filters),
            'generatedAt' => now()->timezone(config('app.timezone'))->format('M d, Y g:i A'),
        ];

        return $user->hasRole(RoleSlug::Requester)
            ? $this->requesterDashboard($user, $filters, $shared)
            : $this->adminDashboard($user, $filters, $shared);
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, status: ?string, bond_type_id: ?int, branch_id: ?int, view: string}
     */
    private function resolveFilters(Request $request, User $user): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(BondRequestStatus::class)],
            'bond_type_id' => ['nullable', 'integer', 'exists:bond_type_masters,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'view' => ['nullable', Rule::in(['overview', 'table'])],
        ]);

        $branchId = BranchScope::canFilterByBranch($user) && isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'status' => $validated['status'] ?? null,
            'bond_type_id' => isset($validated['bond_type_id']) ? (int) $validated['bond_type_id'] : null,
            'branch_id' => $branchId,
            'view' => $validated['view'] ?? 'overview',
        ];
    }

    /**
     * @param  Builder<BondRequest>  $query
     * @param  array{date_from: ?string, date_to: ?string, status: ?string, bond_type_id: ?int, branch_id: ?int}  $filters
     */
    private function applyBondFilters(Builder $query, array $filters, User $user): Builder
    {
        if ($filters['date_from']) {
            $query->whereDate('request_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('request_date', '<=', $filters['date_to']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['bond_type_id']) {
            $query->where('bond_type_id', $filters['bond_type_id']);
        }

        if (! $user->hasRole(RoleSlug::Requester)) {
            BranchScope::applyBondCreatorScope($query, $user, $filters['branch_id']);
        }

        return $query;
    }

    /**
     * @param  array{date_from: ?string, date_to: ?string, status: ?string, bond_type_id: ?int, branch_id: ?int}  $filters
     * @return array<int, string>
     */
    private function filterSummary(array $filters): array
    {
        $summary = [];

        if ($filters['date_from']) {
            $summary[] = 'From '.date('M d, Y', strtotime($filters['date_from']));
        }

        if ($filters['date_to']) {
            $summary[] = 'To '.date('M d, Y', strtotime($filters['date_to']));
        }

        if ($filters['status']) {
            $summary[] = 'Status: '.BondRequestStatus::from($filters['status'])->label();
        }

        if ($filters['bond_type_id']) {
            $bondType = BondTypeMaster::query()->find($filters['bond_type_id']);
            $summary[] = 'Bond Type: '.($bondType?->name ?? 'Unknown');
        }

        if ($filters['branch_id']) {
            $branch = Branch::query()->find($filters['branch_id']);
            $summary[] = 'Branch: '.($branch?->name ?? 'Unknown');
        }

        return $summary;
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function bondTypeOptions(): array
    {
        return BondTypeMaster::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BondTypeMaster $type) => [
                'value' => $type->id,
                'label' => $type->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBondRow(BondRequest $bondRequest, bool $includeAdminFields = false): array
    {
        $row = [
            'id' => $bondRequest->id,
            'bond_number' => $bondRequest->bond_number,
            'bond_type' => $bondRequest->bond_type_label,
            'principal' => $bondRequest->principal?->company_name,
            'amount' => $bondRequest->amount,
            'status' => $bondRequest->status->value,
            'status_label' => $bondRequest->status->label(),
            'status_color' => $bondRequest->status->color(),
            'request_date' => $bondRequest->request_date->format('M d, Y'),
            'has_certificate' => $bondRequest->certificate_path !== null,
        ];

        if ($includeAdminFields) {
            $row['obligee'] = $bondRequest->obligee_name;
            $row['requester'] = $bondRequest->creator?->name;
            $row['branch'] = $bondRequest->creator?->branch?->name;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTransactionRow(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at->format('M d, Y'),
        ];
    }

    /**
     * @param  array{date_from: ?string, date_to: ?string, status: ?string, bond_type_id: ?int}  $filters
     * @param  array<string, mixed>  $shared
     */
    private function requesterDashboard(User $user, array $filters, array $shared): Response
    {
        $bondQuery = $this->applyBondFilters(
            BondRequest::where('created_by', $user->id),
            $filters,
            $user,
        );

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
            ->map(fn (BondRequest $bondRequest) => $this->mapBondRow($bondRequest));

        $transactionQuery = Transaction::where('user_id', $user->id);

        if ($filters['date_from']) {
            $transactionQuery->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $transactionQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $recentTransactions = (clone $transactionQuery)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Transaction $transaction) => $this->mapTransactionRow($transaction));

        $chartData = (clone $bondQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count, $status) => [
                'status' => BondRequestStatus::from($status)->label(),
                'count' => (int) $count,
                'color' => $this->statusColor($status),
            ])->values();

        $payload = [
            ...$shared,
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
            'isRequester' => true,
        ];

        if ($filters['view'] === 'table') {
            $payload['bondRecords'] = (clone $bondQuery)
                ->with(['principal:id,company_name'])
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (BondRequest $bondRequest) => $this->mapBondRow($bondRequest));

            $payload['transactionRecords'] = (clone $transactionQuery)
                ->latest()
                ->paginate(10, ['*'], 'transactions_page')
                ->withQueryString()
                ->through(fn (Transaction $transaction) => $this->mapTransactionRow($transaction));
        }

        return Inertia::render('Dashboard', $payload);
    }

    /**
     * @param  array{date_from: ?string, date_to: ?string, status: ?string, bond_type_id: ?int}  $filters
     * @param  array<string, mixed>  $shared
     */
    private function adminDashboard(User $user, array $filters, array $shared): Response
    {
        $bondQuery = $this->applyBondFilters(BondRequest::query(), $filters, $user);

        $pendingDepositsQuery = Deposit::query()->where('status', DepositStatus::Pending);
        BranchScope::applyUserRelationScope($pendingDepositsQuery, $user, $filters['branch_id']);

        $stats = [
            'total_bonds' => (clone $bondQuery)->count(),
            'pending_approval' => (clone $bondQuery)->where('status', BondRequestStatus::Pending)->count(),
            'approved' => (clone $bondQuery)->where('status', BondRequestStatus::Approved)->count(),
            'notarized' => (clone $bondQuery)->where('status', BondRequestStatus::Notarized)->count(),
            'total_users' => User::where('is_active', true)->count(),
            'pending_deposits' => (clone $pendingDepositsQuery)->count(),
            'total_obligees' => Obligee::count(),
            'total_principals' => Principal::count(),
        ];

        $recentRequests = (clone $bondQuery)
            ->with(['principal:id,company_name', 'creator:id,name,branch_id', 'creator.branch:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (BondRequest $bondRequest) => $this->mapBondRow($bondRequest, includeAdminFields: true));

        $depositQuery = Deposit::with(['user:id,name', 'bankAccount:id,bank_name,account_number'])
            ->where('status', DepositStatus::Pending);

        if ($filters['date_from']) {
            $depositQuery->whereDate('deposit_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $depositQuery->whereDate('deposit_date', '<=', $filters['date_to']);
        }

        BranchScope::applyUserRelationScope($depositQuery, $user, $filters['branch_id']);

        $pendingDeposits = $depositQuery
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

        $monthlyQuery = clone $bondQuery;

        if (! $filters['date_from'] && ! $filters['date_to']) {
            $monthlyQuery->where('request_date', '>=', now()->subMonths(6));
        }

        $monthlyData = $monthlyQuery
            ->get(['request_date'])
            ->groupBy(fn (BondRequest $bondRequest) => $bondRequest->request_date->format('Y-m'))
            ->map(fn ($group, $month) => ['month' => $month, 'count' => $group->count()])
            ->sortKeys()
            ->values();

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

        $payload = [
            ...$shared,
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'pendingDeposits' => $pendingDeposits,
            'chartData' => $chartData,
            'monthlyData' => $monthlyData,
            'activityFeed' => $activityFeed,
            'isRequester' => false,
        ];

        if ($filters['view'] === 'table') {
            $payload['bondRecords'] = (clone $bondQuery)
                ->with(['principal:id,company_name', 'creator:id,name,branch_id', 'creator.branch:id,name'])
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (BondRequest $bondRequest) => $this->mapBondRow($bondRequest, includeAdminFields: true));
        }

        return Inertia::render('AdminDashboard', $payload);
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
