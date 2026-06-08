<?php

namespace App\Http\Middleware;

use App\Models\Maintenance\Branch;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->load('role');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'balance' => (float) $user->balance,
                    'branch_id' => $user->branch_id,
                    'branch_code' => $user->branch_code,
                    'branch_city' => $user->branch_city,
                    'role' => $user->role?->only(['id', 'name', 'slug']),
                    'permissions' => $user->permissionSlugs(),
                ] : null,
            ],
            'navigation' => Navigation::items($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'branchOptions' => fn () => $user ? Branch::activeOptions() : [],
            'currentRoute' => $request->route()?->getName(),
            'notifications' => fn () => $user && Schema::hasTable('notifications') ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'recent' => $user->unreadNotifications()
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn (DatabaseNotification $notification) => [
                        'id' => $notification->id,
                        'type' => $notification->data['type'] ?? '',
                        'title' => $notification->data['title'] ?? '',
                        'message' => $notification->data['message'] ?? '',
                        'url' => $notification->data['url'] ?? route('dashboard'),
                        'created_at' => $notification->created_at->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ] : ($user ? ['unread_count' => 0, 'recent' => []] : null),
        ];
    }
}
