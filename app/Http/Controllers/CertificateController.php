<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    /**
     * List generated certificates.
     *
     * Branch scoping: super admins see certificates across every branch, while
     * all other roles only see certificates created within their own branch.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->hasPermission('bond-requests.view'), 403);

        $isSuperAdmin = $user->hasRole(RoleSlug::SuperAdmin);

        $query = BondRequest::query()
            ->whereNotNull('certificate_path')
            ->with([
                'creator:id,name,branch_id,branch_code',
                'creator.branch:id,name',
                'principal:id,company_name',
            ]);

        if (! $isSuperAdmin) {
            $query->whereHas('creator', fn ($q) => $q->where('branch_id', $user->branch_id));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('bond_number', 'like', "%{$search}%")
                    ->orWhere('car', 'like', "%{$search}%")
                    ->orWhere('obligee_name', 'like', "%{$search}%")
                    ->orWhere('principal_name', 'like', "%{$search}%");
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
                    'request_date' => optional($bondRequest->request_date)->toDateString(),
                    'has_docx' => $bondRequest->docx_path !== null,
                ];
            })
        );

        return Inertia::render('Certifications/Index', [
            'certificates' => $certificates,
            'filters' => $request->only('search'),
            'isSuperAdmin' => $isSuperAdmin,
            'branchName' => $user->branch?->name,
        ]);
    }
}
