<?php

namespace App\Services;

use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GeneratedCertificatePrincipalService
{
    public function paginate(Request $request, int $perPage = 10, ?int $branchId = null): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        $query = DB::table('bond_requests')
            ->join('certificate_versions', 'certificate_versions.bond_request_id', '=', 'bond_requests.id')
            ->whereNotNull('bond_requests.principal_name')
            ->where('bond_requests.principal_name', '!=', '')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('bond_requests.principal_name', 'like', '%'.$search.'%');
            });

        BranchScope::applyBondCreatorTableScope($query, $branchId);

        return $query
            ->select('bond_requests.principal_name as company_name')
            ->selectRaw('COUNT(certificate_versions.id) as certificates_count')
            ->groupBy('bond_requests.principal_name')
            ->orderBy('bond_requests.principal_name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
