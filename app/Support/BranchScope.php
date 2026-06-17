<?php

namespace App\Support;

use App\Enums\RoleSlug;
use App\Models\Maintenance\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BranchScope
{
    public static function canFilterByBranch(User $user): bool
    {
        return $user->hasRole(RoleSlug::Approver);
    }

    public static function showBranchFilter(User $user): bool
    {
        return self::canFilterByBranch($user);
    }

    /**
     * @return array<int, array{value: int, label: string, city: string|null, branch_code: string|null}>
     */
    public static function branchOptions(User $user): array
    {
        return self::canFilterByBranch($user) ? Branch::activeOptions() : [];
    }

    /**
     * Resolve the branch ID that should constrain a query for the given user.
     * Approvers may optionally filter; all other roles are limited to their branch.
     */
    public static function effectiveBranchId(User $user, ?int $requestedBranchId): ?int
    {
        if (self::canFilterByBranch($user)) {
            return $requestedBranchId;
        }

        if ($user->hasRole(RoleSlug::SuperAdmin)) {
            return null;
        }

        return $user->branch_id;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function applyBondCreatorScope(Builder $query, User $user, ?int $requestedBranchId): void
    {
        $branchId = self::effectiveBranchId($user, $requestedBranchId);

        if ($branchId === null) {
            return;
        }

        $query->whereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('branch_id', $branchId));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function applyUserRelationScope(Builder $query, User $user, ?int $requestedBranchId, string $relation = 'user'): void
    {
        if ($user->hasRole(RoleSlug::Requester)) {
            return;
        }

        $branchId = self::effectiveBranchId($user, $requestedBranchId);

        if ($branchId === null) {
            return;
        }

        $query->whereHas($relation, fn (Builder $userQuery) => $userQuery->where('branch_id', $branchId));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function applyTransactionScope(Builder $query, User $user, ?int $requestedBranchId): void
    {
        if ($user->hasRole(RoleSlug::Requester)) {
            if ($user->branch_id !== null) {
                $query->where('branch_id', $user->branch_id);
            }

            return;
        }

        $branchId = self::effectiveBranchId($user, $requestedBranchId);

        if ($branchId === null) {
            return;
        }

        $query->where('branch_id', $branchId);
    }

    public static function branchFilterSummary(?int $branchId): ?string
    {
        if ($branchId === null) {
            return null;
        }

        $branch = Branch::query()->find($branchId);

        return 'Branch: '.($branch?->name ?? 'Unknown');
    }

    /**
     * Limit bond_requests query builder rows to those created by users in the given branch.
     */
    public static function applyBondCreatorTableScope(QueryBuilder $query, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($branchId) {
            $sub->select(DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', 'bond_requests.created_by')
                ->where('users.branch_id', $branchId);
        });
    }
}
