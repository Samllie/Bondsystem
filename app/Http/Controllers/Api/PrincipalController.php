<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrincipalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('bond-requests.view'), 403);

        $search = $request->string('search')->trim()->toString();

        $principals = Principal::query()
            ->when($search !== '', fn ($q) => $q->where('company_name', 'like', '%'.$search.'%'))
            ->orderBy('company_name')
            ->limit(20)
            ->get(['id', 'company_name'])
            ->map(fn (Principal $principal) => [
                'id' => $principal->id,
                'company_name' => $principal->company_name,
                'label' => $principal->company_name,
            ]);

        return response()->json(['data' => $principals]);
    }
}
