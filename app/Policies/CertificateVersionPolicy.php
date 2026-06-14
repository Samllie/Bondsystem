<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\CertificateVersion;
use App\Models\User;

class CertificateVersionPolicy
{
    public function view(User $user, CertificateVersion $certificateVersion): bool
    {
        $certificateVersion->loadMissing('bondRequest');

        return $user->can('viewCertificate', $certificateVersion->bondRequest);
    }

    public function makeCurrent(User $user, CertificateVersion $certificateVersion): bool
    {
        $certificateVersion->loadMissing('bondRequest');

        if (! $user->can('view', $certificateVersion->bondRequest)) {
            return false;
        }

        if ($user->hasRole(RoleSlug::SuperAdmin)) {
            return true;
        }

        return $user->hasPermission('users.view');
    }

    public function delete(User $user, CertificateVersion $certificateVersion): bool
    {
        if ($certificateVersion->is_current) {
            return false;
        }

        $certificateVersion->loadMissing('bondRequest');

        if (! $user->can('view', $certificateVersion->bondRequest)) {
            return false;
        }

        if ($user->hasRole(RoleSlug::SuperAdmin)) {
            return true;
        }

        return $user->hasPermission('bond-requests.approve');
    }
}
