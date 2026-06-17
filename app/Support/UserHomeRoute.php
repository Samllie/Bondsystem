<?php

namespace App\Support;

use App\Models\User;

class UserHomeRoute
{
    public static function url(?User $user): string
    {
        if ($user === null) {
            return route('login', absolute: false);
        }

        if ($user->hasPermission('dashboard.view')) {
            return route('dashboard', absolute: false);
        }

        if ($user->hasPermission('certifications.view-assigned')) {
            return route('certifications.index', absolute: false);
        }

        if ($user->hasPermission('bond-requests.view')) {
            return route('bond-requests.index', absolute: false);
        }

        return route('login', absolute: false);
    }

    public static function label(?User $user): string
    {
        if ($user === null) {
            return 'Back to Login';
        }

        if ($user->hasPermission('dashboard.view')) {
            return 'Back to Dashboard';
        }

        if ($user->hasPermission('certifications.view-assigned')) {
            return 'Back to Confirmations';
        }

        if ($user->hasPermission('bond-requests.view')) {
            return 'Back to Bond Requests';
        }

        return 'Back';
    }
}
