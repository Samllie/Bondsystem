<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\User;

class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('certificate-templates.view');
    }

    public function view(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->hasPermission('certificate-templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('certificate-templates.manage');
    }

    public function activate(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->hasPermission('certificate-templates.manage');
    }

    public function archive(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->hasPermission('certificate-templates.manage');
    }

    public function download(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->hasPermission('certificate-templates.view');
    }
}
