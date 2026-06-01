<?php

namespace App\Http\Controllers;

use App\Enums\BondRequestStatus;
use App\Enums\RoleSlug;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Support\AmountInWords;
use App\Http\Requests\BondRequest\StoreBondRequestRequest;
use App\Http\Requests\BondRequest\UpdateBondRequestRequest;
use App\Models\BondRequest;
use App\Models\PaymentHistory;
use App\Models\Principal;
use App\Services\ActivityLogger;
use App\Services\KycObligeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BondRequestController extends Controller
{
    public function __construct(private KycObligeeService $kycObligeeService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BondRequest::class);

        $query = BondRequest::query()
            ->with(['principal:id,company_name', 'creator:id,name']);

        if ($request->user()->hasRole(RoleSlug::Requester)) {
            $query->where('created_by', $request->user()->id);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('bond_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('obligee_name', 'like', "%{$search}%")
                    ->orWhereHas('principal', fn ($q) => $q->where('company_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($bondTypeId = $request->integer('bond_type_id')) {
            $query->where('bond_type_id', $bondTypeId);
        }

        $bondRequests = $query->latest()->paginate(10)->withQueryString();

        $bondRequests->getCollection()->transform(function (BondRequest $bondRequest) {
            $bondRequest->setRelation('obligee', (object) $bondRequest->obligeeSummary());

            return $bondRequest;
        });

        return Inertia::render('BondRequests/Index', [
            'bondRequests' => $bondRequests,
            'filters' => $request->only(['search', 'status', 'bond_type_id']),
            'statusOptions' => BondRequestStatus::options(),
            'bondTypeOptions' => $this->bondTypeOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', BondRequest::class);

        return Inertia::render('BondRequests/Form', [
            'bondRequest' => null,
            'selectedPrincipal' => null,
            'selectedObligee' => null,
            'bondTypeOptions' => $this->bondTypeOptions(),
            'signatoryOptions' => $this->signatoryOptions(),
            'notaryOptions' => $this->notaryOptions(),
            'isRequester' => $request->user()->hasRole(RoleSlug::Requester),
        ]);
    }

    public function store(StoreBondRequestRequest $request): RedirectResponse
    {
        $bondRequest = BondRequest::create(
            $this->bondRequestAttributes($request)
        );

        PaymentHistory::create([
            'user_id' => $request->user()->id,
            'bond_request_id' => $bondRequest->id,
            'amount' => $bondRequest->amount,
            'description' => "Bond request payment — {$bondRequest->bond_number}",
            'paid_at' => now(),
        ]);

        ActivityLogger::log('created', "Bond request {$bondRequest->bond_number} created.", $bondRequest);

        return redirect()->route('bond-requests.show', $bondRequest)
            ->with('success', 'Bond request created successfully.');
    }

    public function show(BondRequest $bondRequest): Response
    {
        $this->authorize('view', $bondRequest);

        $bondRequest->load(['principal', 'bondTypeMaster', 'signatory', 'notary', 'creator:id,name', 'approver:id,name']);
        $bondRequest->setRelation('obligee', (object) $bondRequest->obligeeSummary());
        $bondRequest->append(['status_label', 'status_color', 'bond_type_label']);

        return Inertia::render('BondRequests/Show', [
            'bondRequest' => $bondRequest,
            'canUpdate' => request()->user()->can('update', $bondRequest),
            'canDelete' => request()->user()->can('delete', $bondRequest),
            'canApprove' => request()->user()->hasPermission('bond-requests.approve')
                && $bondRequest->status === BondRequestStatus::Pending,
            'canNotarize' => request()->user()->hasPermission('bond-requests.notarize')
                && $bondRequest->status === BondRequestStatus::Approved,
        ]);
    }

    public function edit(Request $request, BondRequest $bondRequest): Response
    {
        $this->authorize('update', $bondRequest);

        $bondRequest->load(['principal', 'bondTypeMaster', 'signatory', 'notary']);

        return Inertia::render('BondRequests/Form', [
            'bondRequest' => $bondRequest,
            'selectedPrincipal' => $bondRequest->principal?->only(['id', 'company_name']),
            'selectedObligee' => [
                'id' => $bondRequest->obligee_id,
                'company_name' => $bondRequest->obligee_name,
                'label' => $bondRequest->obligee_name,
            ],
            'bondTypeOptions' => $this->bondTypeOptions(),
            'signatoryOptions' => $this->signatoryOptions(),
            'notaryOptions' => $this->notaryOptions(),
            'isRequester' => $request->user()->hasRole(RoleSlug::Requester),
        ]);
    }

    public function update(UpdateBondRequestRequest $request, BondRequest $bondRequest): RedirectResponse
    {
        $bondRequest->update(
            $this->bondRequestAttributes($request, $bondRequest)
        );

        ActivityLogger::log('updated', "Bond request {$bondRequest->bond_number} updated.", $bondRequest);

        return redirect()->route('bond-requests.show', $bondRequest)
            ->with('success', 'Bond request updated successfully.');
    }

    public function destroy(BondRequest $bondRequest): RedirectResponse
    {
        $this->authorize('delete', $bondRequest);

        $number = $bondRequest->bond_number;
        $bondRequest->delete();

        ActivityLogger::log('deleted', "Bond request {$number} deleted.", $bondRequest);

        return redirect()->route('bond-requests.index')
            ->with('success', 'Bond request deleted successfully.');
    }

    public function approve(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.approve'), 403);
        abort_unless($bondRequest->status === BondRequestStatus::Pending, 422);

        $bondRequest->update([
            'status' => BondRequestStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLogger::log('approved', "Bond request {$bondRequest->bond_number} approved.", $bondRequest);

        return back()->with('success', 'Bond request approved.');
    }

    public function reject(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.approve'), 403);
        abort_unless($bondRequest->status === BondRequestStatus::Pending, 422);

        $bondRequest->update([
            'status' => BondRequestStatus::Rejected,
            'remarks' => $request->input('remarks', $bondRequest->remarks),
        ]);

        ActivityLogger::log('rejected', "Bond request {$bondRequest->bond_number} rejected.", $bondRequest);

        return back()->with('success', 'Bond request rejected.');
    }

    public function notarize(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.notarize'), 403);
        abort_unless($bondRequest->status === BondRequestStatus::Approved, 422);

        $bondRequest->update(['status' => BondRequestStatus::Notarized]);

        ActivityLogger::log('notarized', "Bond request {$bondRequest->bond_number} notarized.", $bondRequest);

        return back()->with('success', 'Bond request marked as notarized.');
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function bondTypeOptions(): array
    {
        return BondTypeMaster::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (BondTypeMaster $type) => [
                'value' => $type->id,
                'label' => $type->name,
                'code' => $type->code,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string, position: string|null}>
     */
    private function signatoryOptions(): array
    {
        return Signatory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn (Signatory $signatory) => [
                'value' => $signatory->id,
                'label' => $signatory->name,
                'position' => $signatory->position,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function notaryOptions(): array
    {
        return Notary::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'commission_number'])
            ->map(fn (Notary $notary) => [
                'value' => $notary->id,
                'label' => $notary->name,
                'commission_number' => $notary->commission_number,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function bondRequestAttributes(Request $request, ?BondRequest $bondRequest = null): array
    {
        $obligee = $this->kycObligeeService->find($request->integer('obligee_id'));
        $bondType = BondTypeMaster::query()->findOrFail($request->integer('bond_type_id'));
        $signatory = Signatory::query()->findOrFail($request->integer('signatory_id'));
        $obligeeName = $request->string('obligee_name')->trim()->toString();

        $attributes = [
            ...$request->validated(),
            'bond_type' => $bondType->code ?? $bondType->name,
            'obligee_name' => $obligeeName !== '' ? $obligeeName : ($obligee['company_name'] ?? ''),
            'amount_in_words' => AmountInWords::format($request->input('amount')),
            'signatory_position' => $signatory->position,
        ];

        if ($bondRequest === null) {
            $attributes['created_by'] = $request->user()->id;
            $attributes['status'] = BondRequestStatus::Pending;
        }

        return $attributes;
    }
}
