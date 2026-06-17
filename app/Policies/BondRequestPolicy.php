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
        if (! $user->hasPermission('bond-requests.view')) {
            return false;
        }

        return $this->canAccessBondRequest($user, $bondRequest);
    }

    /**
     * Access to the generated certificate file is branch-scoped for most roles.
     * Signatory account levels may view any generated certificate.
     */
    public function viewCertificate(User $user, BondRequest $bondRequest): bool
    {
        if ($user->hasPermission('certifications.view-assigned') && ! $user->hasPermission('bond-requests.view')) {
            return $bondRequest->certificate_path !== null;
        }

        if (! $user->hasPermission('bond-requests.view')) {
            return false;
        }

        if ($user->hasRole(RoleSlug::SuperAdmin)) {
            return true;
        }

        return $bondRequest->creator?->branch_id === $user->branch_id;
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

        if ($user->hasRole(RoleSlug::SuperAdmin) || $user->hasRole(RoleSlug::Approver)) {
            return true;
        }

        return $bondRequest->creator?->branch_id === $user->branch_id;
    }

    public function delete(User $user, BondRequest $bondRequest): bool
    {
        return $user->hasPermission('bond-requests.delete');
    }

    private function canAccessBondRequest(User $user, BondRequest $bondRequest): bool
    {
        if ($user->hasRole(RoleSlug::Requester)) {
            return $bondRequest->created_by === $user->id;
        }

        if ($user->hasRole(RoleSlug::SuperAdmin) || $user->hasRole(RoleSlug::Approver)) {
            return true;
        }

        return $bondRequest->creator?->branch_id === $user->branch_id;
    }
}
