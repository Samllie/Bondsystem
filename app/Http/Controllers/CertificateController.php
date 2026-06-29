<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    /**
     * Branch-scoped certificate list for requesters and approvers.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Notary accounts see all confirmations like super admin
        if ($user->hasRole(RoleSlug::Notary)) {
            return $this->renderIndex($request, scoped: false, attorney: true);
        }

        if ($user->hasPermission('certifications.view-assigned') && ! $user->hasPermission('bond-requests.view')) {
            return $this->renderIndex($request, scoped: false, attorney: true);
        }

        abort_unless($user->hasPermission('bond-requests.view'), 403);

if ($user->hasRole(RoleSlug::Encoder)) {
    return $this->renderIndex($request, scoped: false);
}

return $this->renderIndex($request, scoped: true);
    }

    /**
     * Cross-branch certificate registry for Maintenance → Certification.
     */
    public function maintenanceIndex(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.view'), 403);

        return $this->renderIndex($request, scoped: false);
    }

    private function renderIndex(Request $request, bool $scoped, bool $attorney = false): Response
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: null;

        $query = BondRequest::query()
            ->whereNotNull('certificate_path')
            ->with([
                'creator:id,name,branch_id,branch_code',
                'creator.branch:id,name',
                'approver:id,name',
                'principal:id,company_name',
                'currentCertificateVersion:id,bond_request_id,confirmation_number,version_number',
            ]);

        if ($scoped) {
            BranchScope::applyBondCreatorScope($query, $user, $branchId);
        } elseif ($branchId !== null) {
            $query->whereHas('creator', fn ($creatorQuery) => $creatorQuery->where('branch_id', $branchId));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('bond_number', 'like', "%{$search}%")
                    ->orWhere('car', 'like', "%{$search}%")
                    ->orWhere('obligee_name', 'like', "%{$search}%")
                    ->orWhere('principal_name', 'like', "%{$search}%")
                    ->orWhereHas('currentCertificateVersion', fn ($versionQuery) => $versionQuery
                        ->where(function ($versionSearch) use ($search) {
                            $versionSearch->where('confirmation_number', 'like', "%{$search}%")
                                ->orWhere('verification_token', 'like', "%{$search}%");
                        }));
            });
        }

        $certificates = $query->latest()->paginate(10)->withQueryString();

        $certificates->setCollection(
            $certificates->getCollection()->map(function (BondRequest $bondRequest): array {
                return [
                    'id' => $bondRequest->id,
                    'obligee_name' => $bondRequest->obligee_name,
                    'principal_name' => $bondRequest->principal?->company_name ?? $bondRequest->principal_name,
                    'bond_label' => $bondRequest->bond_label,
                    'bond_number' => $bondRequest->bond_number,
                    'certificate_type_label' => $bondRequest->certificate_type_label,
                    'branch_name' => $bondRequest->creator?->branch?->name ?? '—',
                    'requester_name' => $bondRequest->creator?->name ?? '—',
                    'approver_name' => $bondRequest->approver?->name ?? '—',
                    'request_date' => optional($bondRequest->request_date)->toDateString(),
                    'confirmation_number' => $bondRequest->currentCertificateVersion?->confirmation_number,
                    'has_docx' => $bondRequest->docx_path !== null,
                ];
            })
        );

        $canViewAllBranches = $attorney || ! $scoped
            ? true
            : ($user->hasRole(RoleSlug::SuperAdmin) || BranchScope::canFilterByBranch($user));

        $showBranchFilter = $attorney || ! $scoped
            ? true
            : BranchScope::showBranchFilter($user);

        $context = $attorney ? 'attorney' : ($scoped ? 'user' : 'maintenance');

        return Inertia::render('Certifications/Index', [
            'certificates' => $certificates,
            'filters' => $request->only('search', 'branch_id'),
            'canViewAllBranches' => $canViewAllBranches,
            'branchName' => $user->branch?->name,
            'branchOptions' => $scoped && ! $attorney ? BranchScope::branchOptions($user) : Branch::activeOptions(),
            'showBranchFilter' => $showBranchFilter,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('M d, Y g:i A'),
            'context' => $context,
            'listUrl' => $attorney || $scoped
                ? route('certifications.index')
                : route('maintenance.certifications.index'),
            'pageTitle' => 'Confirmations',
            'scopeMessage' => $attorney
                ? 'Showing all generated confirmations across every branch. Use the branch filter to narrow results.'
                : ($scoped
                    ? ($canViewAllBranches
                        ? ($showBranchFilter
                            ? 'Showing generated confirmations across all branches. Use the branch filter to narrow results.'
                            : 'Showing generated confirmations across all branches.')
                        : 'Showing generated confirmations for your branch'.($user->branch?->name ? " ({$user->branch->name})" : '').'.')
                    : 'Showing all generated confirmations across every branch. Use the branch filter to narrow results.'),
            'readOnly' => $attorney,
        ]);
    }
}
