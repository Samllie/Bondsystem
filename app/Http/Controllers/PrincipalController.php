<?php

namespace App\Http\Controllers;

use App\Http\Requests\Principal\StorePrincipalRequest;
use App\Http\Requests\Principal\UpdatePrincipalRequest;
use App\Models\Principal;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrincipalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Principal::class);

        $search = $request->string('search')->trim()->toString();

        $principals = Principal::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('company_name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Principals/Index', [
            'principals' => $principals,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Principal::class);

        return Inertia::render('Principals/Form', ['principal' => null]);
    }

    public function store(StorePrincipalRequest $request): RedirectResponse
    {
        $principal = Principal::create($request->validated());

        ActivityLogger::log('created', "Principal {$principal->company_name} created.", $principal);

        return redirect()->route('principals.index')->with('success', 'Principal created successfully.');
    }

    public function show(Principal $principal): Response
    {
        $this->authorize('view', $principal);

        return Inertia::render('Principals/Show', [
            'principal' => $principal,
            'canUpdate' => request()->user()->can('update', $principal),
            'canDelete' => request()->user()->can('delete', $principal),
        ]);
    }

    public function edit(Principal $principal): Response
    {
        $this->authorize('update', $principal);

        return Inertia::render('Principals/Form', ['principal' => $principal]);
    }

    public function update(UpdatePrincipalRequest $request, Principal $principal): RedirectResponse
    {
        $principal->update($request->validated());

        ActivityLogger::log('updated', "Principal {$principal->company_name} updated.", $principal);

        return redirect()->route('principals.index')->with('success', 'Principal updated successfully.');
    }

    public function destroy(Principal $principal): RedirectResponse
    {
        $this->authorize('delete', $principal);

        if ($principal->bondRequests()->exists()) {
            return back()->with('error', 'Cannot delete principal with existing bond requests.');
        }

        $name = $principal->company_name;
        $principal->delete();

        ActivityLogger::log('deleted', "Principal {$name} deleted.", $principal);

        return redirect()->route('principals.index')->with('success', 'Principal deleted successfully.');
    }
}
