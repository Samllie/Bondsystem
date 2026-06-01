<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Principal;
use App\Services\KycObligeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObligeeController extends Controller
{
    public function __construct(private KycObligeeService $kycObligeeService) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('bond-requests.view'), 403);

        $search = $request->string('search')->trim()->toString();

        return response()->json([
            'data' => $this->kycObligeeService->search($search !== '' ? $search : null),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('bond-requests.view'), 403);

        $obligee = $this->kycObligeeService->find($id);

        abort_unless($obligee !== null, 404);

        return response()->json(['data' => $obligee]);
    }
}
