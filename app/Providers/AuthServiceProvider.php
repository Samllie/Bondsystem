<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BondRequest;
use App\Models\CertificateTemplate;
use App\Models\CertificateVersion;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BondRequestPolicy;
use App\Policies\CertificateTemplatePolicy;
use App\Policies\CertificateVersionPolicy;
use App\Policies\ObligeePolicy;
use App\Policies\PrincipalPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        AuditLog::class => AuditLogPolicy::class,
        BondRequest::class => BondRequestPolicy::class,
        CertificateTemplate::class => CertificateTemplatePolicy::class,
        CertificateVersion::class => CertificateVersionPolicy::class,
        Obligee::class => ObligeePolicy::class,
        Principal::class => PrincipalPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
