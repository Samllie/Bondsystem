<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->hasPermission('users.manage')) {
            return false;
        }

        if ($model->hasRole(RoleSlug::SuperAdmin) && ! $user->hasRole(RoleSlug::SuperAdmin)) {
            return false;
        }

        return true;
    }
}
