<?php

namespace App\Providers;

use App\Models\BondRequest;
use App\Models\CertificateTemplate;
use App\Models\Obligee;
use App\Models\Principal;
use App\Models\User;
use App\Policies\BondRequestPolicy;
use App\Policies\CertificateTemplatePolicy;
use App\Policies\ObligeePolicy;
use App\Policies\PrincipalPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        BondRequest::class => BondRequestPolicy::class,
        CertificateTemplate::class => CertificateTemplatePolicy::class,
        Obligee::class => ObligeePolicy::class,
        Principal::class => PrincipalPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
