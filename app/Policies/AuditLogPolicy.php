<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleSlug::SuperAdmin);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(RoleSlug::SuperAdmin);
    }
}
