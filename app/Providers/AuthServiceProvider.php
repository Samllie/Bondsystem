<?php

namespace App\Providers;

use App\Models\BondRequest;
use App\Models\Obligee;
use App\Models\Principal;
use App\Policies\BondRequestPolicy;
use App\Policies\ObligeePolicy;
use App\Policies\PrincipalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        BondRequest::class => BondRequestPolicy::class,
        Obligee::class => ObligeePolicy::class,
        Principal::class => PrincipalPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
