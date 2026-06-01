<?php

namespace App\Policies;

use App\Models\Principal;
use App\Models\User;

class PrincipalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('principals.view');
    }

    public function view(User $user, Principal $principal): bool
    {
        return $user->hasPermission('principals.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('principals.create');
    }

    public function update(User $user, Principal $principal): bool
    {
        return $user->hasPermission('principals.update');
    }

    public function delete(User $user, Principal $principal): bool
    {
        return $user->hasPermission('principals.delete');
    }
}
