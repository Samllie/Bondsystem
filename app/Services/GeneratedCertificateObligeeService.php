<?php

namespace App\Services;

use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GeneratedCertificateObligeeService
{
    public function paginateFromKyc(Request $request, int $perPage = 10, ?int $branchId = null): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        $query = DB::table('bond_requests')
            ->join('certificate_versions', 'certificate_versions.bond_request_id', '=', 'bond_requests.id')
            ->whereNotNull('bond_requests.obligee_id')
            ->whereNotNull('bond_requests.obligee_name')
            ->where('bond_requests.obligee_name', '!=', '')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('bond_requests.obligee_name', 'like', '%'.$search.'%')
                        ->orWhere('bond_requests.obligee_id', 'like', '%'.$search.'%');
                });
            });

        BranchScope::applyBondCreatorTableScope($query, $branchId);

        return $query
            ->select('bond_requests.obligee_id', 'bond_requests.obligee_name as company_name')
            ->selectRaw('COUNT(certificate_versions.id) as certificates_count')
            ->groupBy('bond_requests.obligee_id', 'bond_requests.obligee_name')
            ->orderBy('bond_requests.obligee_name')
            ->paginate($perPage, ['*'], 'cert_kyc_page')
            ->withQueryString();
    }

    public function paginateTyped(Request $request, int $perPage = 10, ?int $branchId = null): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        $query = DB::table('bond_requests')
            ->join('certificate_versions', 'certificate_versions.bond_request_id', '=', 'bond_requests.id')
            ->whereNull('bond_requests.obligee_id')
            ->whereNotNull('bond_requests.obligee_name')
            ->where('bond_requests.obligee_name', '!=', '')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('bond_requests.obligee_name', 'like', '%'.$search.'%');
            });

        BranchScope::applyBondCreatorTableScope($query, $branchId);

        return $query
            ->select('bond_requests.obligee_name as company_name')
            ->selectRaw('COUNT(certificate_versions.id) as certificates_count')
            ->groupBy('bond_requests.obligee_name')
            ->orderBy('bond_requests.obligee_name')
            ->paginate($perPage, ['*'], 'cert_typed_page')
            ->withQueryString();
    }
}
