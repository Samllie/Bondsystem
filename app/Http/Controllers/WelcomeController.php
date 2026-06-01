<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('WelcomeSplash', [
            'userName' => $user->name,
            'redirectTo' => $this->resolveRedirectUrl($request),
        ]);
    }

    private function resolveRedirectUrl(Request $request): string
    {
        if ($request->session()->has('url.intended')) {
            return $request->session()->get('url.intended');
        }

        $user = $request->user();

        if ($user->hasPermission('dashboard.view')) {
            return route('dashboard', absolute: false);
        }

        return route('bond-requests.index', absolute: false);
    }
}
