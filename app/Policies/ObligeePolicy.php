<?php

namespace App\Policies;

use App\Models\Obligee;
use App\Models\User;

class ObligeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('obligees.view');
    }

    public function view(User $user, Obligee $obligee): bool
    {
        return $user->hasPermission('obligees.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('obligees.create');
    }

    public function update(User $user, Obligee $obligee): bool
    {
        return $user->hasPermission('obligees.update');
    }

    public function delete(User $user, Obligee $obligee): bool
    {
        return $user->hasPermission('obligees.delete');
    }
}
