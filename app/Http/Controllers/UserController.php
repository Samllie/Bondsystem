<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Http\Requests\StoreUserRequest;
use App\Models\Maintenance\Branch;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['role:id,name,slug', 'branch:id,name'])
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('branch_city', 'like', "%{$search}%")
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only('search'),
            'canManage' => $request->user()->can('create', User::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Form', [
            'user' => null,
            'roleOptions' => $this->roleOptions(),
            'branchOptions' => Branch::activeOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->except(['password', 'password_confirmation']),
            'password' => Hash::make($request->string('password')->toString()),
            'email_verified_at' => now(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $role = Role::query()->find($request->integer('role_id'));

        if ($role?->slug === RoleSlug::Notary->value) {
            Signatory::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'is_active' => true,
            ]);

            Notary::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'is_active' => true,
            ]);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'value' => $role->id,
                'label' => $role->name,
            ])
            ->all();
    }
}
