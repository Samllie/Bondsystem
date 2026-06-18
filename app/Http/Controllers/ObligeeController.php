<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Http\Requests\Obligee\StoreObligeeRequest;
use App\Http\Requests\Obligee\UpdateObligeeRequest;
use App\Models\Obligee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GeneratedCertificateObligeeService;
use App\Services\KycObligeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObligeeController extends Controller
{
    public function __construct(
        private KycObligeeService $kycObligeeService,
        private GeneratedCertificateObligeeService $generatedCertificateObligeeService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Obligee::class);

        $search = $request->string('search')->trim()->toString();
        $user = $request->user();
        $user->loadMissing('branch');

        if ($this->usesGlobalConfirmationRecords($user)) {
            return Inertia::render('Obligees/Index', [
                'kycObligees' => $this->kycObligeeService->paginate($search !== '' ? $search : null),
                'certificateObligeesFromKyc' => $this->generatedCertificateObligeeService->paginateFromKyc($request),
                'certificateObligeesTyped' => $this->generatedCertificateObligeeService->paginateTyped($request),
                'filters' => $request->only(['search']),
                'kycView' => true,
                'branchConfirmationsView' => false,
            ]);
        }

        if ($this->usesBranchConfirmationRecords($user)) {
            return Inertia::render('Obligees/Index', [
                'certificateObligeesFromKyc' => $this->generatedCertificateObligeeService->paginateFromKyc($request, branchId: $user->branch_id),
                'certificateObligeesTyped' => $this->generatedCertificateObligeeService->paginateTyped($request, branchId: $user->branch_id),
                'filters' => $request->only(['search']),
                'kycView' => false,
                'branchConfirmationsView' => true,
                'branchName' => $user->branch?->name,
            ]);
        }

        $obligees = Obligee::query()
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

        return Inertia::render('Obligees/Index', [
            'obligees' => $obligees,
            'filters' => $request->only(['search']),
            'kycView' => false,
            'branchConfirmationsView' => false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Obligee::class);

        return Inertia::render('Obligees/Form', ['obligee' => null]);
    }

    public function store(StoreObligeeRequest $request): RedirectResponse
    {
        $obligee = Obligee::create($request->validated());

        ActivityLogger::log('created', "Obligee {$obligee->company_name} created.", $obligee);

        return redirect()->route('obligees.index')->with('success', 'Obligee created successfully.');
    }

    public function show(Obligee $obligee): Response
    {
        $this->authorize('view', $obligee);

        return Inertia::render('Obligees/Show', [
            'obligee' => $obligee,
            'canUpdate' => request()->user()->can('update', $obligee),
            'canDelete' => request()->user()->can('delete', $obligee),
        ]);
    }

    public function edit(Obligee $obligee): Response
    {
        $this->authorize('update', $obligee);

        return Inertia::render('Obligees/Form', ['obligee' => $obligee]);
    }

    public function update(UpdateObligeeRequest $request, Obligee $obligee): RedirectResponse
    {
        $obligee->update($request->validated());

        ActivityLogger::log('updated', "Obligee {$obligee->company_name} updated.", $obligee);

        return redirect()->route('obligees.index')->with('success', 'Obligee updated successfully.');
    }

    public function destroy(Obligee $obligee): RedirectResponse
    {
        $this->authorize('delete', $obligee);

        if ($obligee->bondRequests()->exists()) {
            return back()->with('error', 'Cannot delete obligee with existing bond requests.');
        }

        $name = $obligee->company_name;
        $obligee->delete();

        ActivityLogger::log('deleted', "Obligee {$name} deleted.", $obligee);

        return redirect()->route('obligees.index')->with('success', 'Obligee deleted successfully.');
    }

    private function usesGlobalConfirmationRecords(User $user): bool
    {
        return $user->hasRole(RoleSlug::SuperAdmin) || $user->hasRole(RoleSlug::Approver);
    }

    private function usesBranchConfirmationRecords(User $user): bool
    {
        return $user->hasRole(RoleSlug::Requester) || $user->hasRole(RoleSlug::Encoder);
    }
}
