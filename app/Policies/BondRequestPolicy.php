<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\User;

class BondRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bond-requests.view');
    }

    public function view(User $user, BondRequest $bondRequest): bool
    {
        if ($user->hasPermission('bond-requests.view')) {
            if ($user->hasRole(RoleSlug::Requester)) {
                return $bondRequest->created_by === $user->id;
            }

            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bond-requests.create');
    }

    public function update(User $user, BondRequest $bondRequest): bool
    {
        if (! $user->hasPermission('bond-requests.update')) {
            return false;
        }

        if ($user->hasRole(RoleSlug::Requester)) {
            return $bondRequest->created_by === $user->id
                && in_array($bondRequest->status->value, ['draft', 'pending'], true);
        }

        return true;
    }

    public function delete(User $user, BondRequest $bondRequest): bool
    {
        return $user->hasPermission('bond-requests.delete');
    }
}
