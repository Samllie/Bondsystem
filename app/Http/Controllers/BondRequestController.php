<?php

namespace App\Http\Controllers;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\RoleSlug;
use App\Http\Requests\BondRequest\ApproveBondRequestRequest;
use App\Http\Requests\BondRequest\StoreBondRequestRequest;
use App\Http\Requests\BondRequest\UpdateBondRequestRequest;
use App\Models\BondRequest;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\PaymentHistory;
use App\Services\ActivityLogger;
use App\Services\CertificateGenerationService;
use App\Services\KycObligeeService;
use App\Support\AmountInWords;
use App\Support\BondNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BondRequestController extends Controller
{
    public function __construct(
        private KycObligeeService $kycObligeeService,
        private CertificateGenerationService $certificateGenerationService,
    ) {}

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
            'certificateTypeOptions' => CertificateType::options(),
            'requesterBranchCode' => BondNumberGenerator::branchCodeFor($request->user()->load('branch')),
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

        $bondRequest->load(['principal', 'bondTypeMaster', 'signatory', 'notary', 'creator:id,name,branch_id', 'creator.branch', 'approver:id,name']);
        $bondRequest->setRelation('obligee', (object) $bondRequest->obligeeSummary());
        $bondRequest->append(['status_label', 'status_color', 'bond_type_label', 'certificate_type_label', 'bond_label']);

        $canApprove = request()->user()->hasPermission('bond-requests.approve')
            && $bondRequest->status === BondRequestStatus::Pending;

        $canGenerateCertificate = request()->user()->hasPermission('bond-requests.approve')
            && in_array($bondRequest->status->value, [BondRequestStatus::Approved->value, BondRequestStatus::Notarized->value], true);

        $needsOptions = $canApprove || $canGenerateCertificate;

        return Inertia::render('BondRequests/Show', [
            'bondRequest' => $bondRequest,
            'supportingDocumentUrl' => $bondRequest->supporting_document_path
                ? Storage::disk('public')->url($bondRequest->supporting_document_path)
                : null,
            'canUpdate' => request()->user()->can('update', $bondRequest),
            'canDelete' => request()->user()->can('delete', $bondRequest),
            'canApprove' => $canApprove,
            'canNotarize' => request()->user()->hasPermission('bond-requests.notarize')
                && $bondRequest->status === BondRequestStatus::Approved,
            'canGenerateCertificate' => $canGenerateCertificate,
            'hasCertificate' => $bondRequest->certificate_path !== null,
            'hasDocx' => $bondRequest->docx_path !== null,
            'signatoryOptions' => $needsOptions ? $this->signatoryOptions() : [],
            'notaryOptions' => $needsOptions ? $this->notaryOptions() : [],
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
            'certificateTypeOptions' => CertificateType::options(),
            'supportingDocumentUrl' => $bondRequest->supporting_document_path
                ? Storage::disk('public')->url($bondRequest->supporting_document_path)
                : null,
            'requesterBranchCode' => BondNumberGenerator::branchCodeFor($request->user()->load('branch')),
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

    public function approve(ApproveBondRequestRequest $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($bondRequest->status === BondRequestStatus::Pending, 422);

        $signatory = Signatory::query()->findOrFail($request->integer('signatory_id'));

        $bondRequest->update([
            'status' => BondRequestStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'signatory_id' => $signatory->id,
            'signatory_position' => $signatory->position,
            'notary_id' => $request->integer('notary_id'),
            'doc_no' => $request->input('doc_no'),
            'page_no' => $request->input('page_no'),
            'book_no' => $request->input('book_no'),
            'series_year' => $request->input('series_year'),
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

    public function generateCertificate(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.approve'), 403);
        abort_unless(
            in_array($bondRequest->status->value, [BondRequestStatus::Approved->value, BondRequestStatus::Notarized->value], true),
            422,
            'Certificate can only be generated for approved or notarized bond requests.',
        );

        $validated = $request->validate([
            'signatory_id' => ['required', 'integer', 'exists:signatories,id'],
            'notary_id' => ['required', 'integer', 'exists:notaries,id'],
            'doc_no' => ['required', 'string', 'max:50'],
            'page_no' => ['required', 'string', 'max:50'],
            'book_no' => ['required', 'string', 'max:50'],
            'series_year' => ['required', 'string', 'size:4'],
        ]);

        $signatory = Signatory::findOrFail($validated['signatory_id']);

        $bondRequest->update([
            'signatory_id' => $signatory->id,
            'signatory_position' => $signatory->position,
            'notary_id' => $validated['notary_id'],
            'doc_no' => $validated['doc_no'],
            'page_no' => $validated['page_no'],
            'book_no' => $validated['book_no'],
            'series_year' => $validated['series_year'],
        ]);

        try {
            $this->certificateGenerationService->generate($bondRequest->fresh());
        } catch (\Throwable $e) {
            return back()->with('error', 'Certificate generation failed: '.$e->getMessage());
        }

        ActivityLogger::log('generated', "Certificate generated for bond request {$bondRequest->bond_number}.", $bondRequest);

        return back()->with('success', 'Certificate generated successfully.');
    }

    public function viewCertificate(Request $request, BondRequest $bondRequest): BinaryFileResponse
    {
        $this->authorize('view', $bondRequest);
        abort_if($bondRequest->certificate_path === null, 404, 'No certificate has been generated yet.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($bondRequest->certificate_path), 404, 'Certificate file not found.');

        $extension = pathinfo($bondRequest->certificate_path, PATHINFO_EXTENSION);
        $filename = "{$bondRequest->bond_number}_certificate.{$extension}";
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream';

        return response()->file(
            $disk->path($bondRequest->certificate_path),
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ],
        );
    }

    public function downloadCertificate(Request $request, BondRequest $bondRequest): StreamedResponse
    {
        $this->authorize('view', $bondRequest);
        abort_if($bondRequest->certificate_path === null, 404, 'No certificate has been generated yet.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($bondRequest->certificate_path), 404, 'Certificate file not found.');

        $extension = pathinfo($bondRequest->certificate_path, PATHINFO_EXTENSION);
        $filename = "{$bondRequest->bond_number}_certificate.{$extension}";

        return $disk->download($bondRequest->certificate_path, $filename);
    }

    public function downloadDocx(Request $request, BondRequest $bondRequest): StreamedResponse
    {
        $this->authorize('view', $bondRequest);
        abort_if($bondRequest->docx_path === null, 404, 'No DOCX has been generated yet.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($bondRequest->docx_path), 404, 'DOCX file not found.');

        $filename = "{$bondRequest->bond_number}_certificate.docx";

        return $disk->download($bondRequest->docx_path, $filename);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function bondTypeOptions(): array
    {
        return BondTypeMaster::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'bond_serial'])
            ->map(fn (BondTypeMaster $type) => [
                'value' => $type->id,
                'label' => $type->name,
                'code' => $type->code,
                'bond_serial' => $type->bond_serial,
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
        $obligeeName = $request->string('obligee_name')->trim()->toString();
        $certificateType = $request->enum('certificate_type', CertificateType::class);

        $attributes = [
            ...$request->validated(),
            'obligee_name' => $obligeeName !== '' ? $obligeeName : ($obligee['company_name'] ?? ''),
            'amount_in_words' => AmountInWords::format($request->input('amount')),
        ];

        if ($certificateType === CertificateType::CarCertificate) {
            $car = $request->string('car')->trim()->toString();
            $attributes['car'] = $car;
            $attributes['bond_number'] = $car;
            $attributes['bond_type'] = 'CAR';
            $attributes['bond_type_id'] = null;
            $attributes['authorized_representative'] = $request->string('authorized_representative')->trim()->toString();
        } else {
            $bondType = BondTypeMaster::query()->findOrFail($request->integer('bond_type_id'));
            $attributes['bond_type'] = $bondType->name;
            $attributes['bond_number'] = BondNumberGenerator::fromBondType($bondType);
            $attributes['car'] = null;
            $attributes['authorized_representative'] = null;
        }

        $attributes['tin'] = $request->string('tin')->trim()->toString();

        unset($attributes['supporting_document']);

        if ($request->hasFile('supporting_document')) {
            if ($bondRequest?->supporting_document_path) {
                Storage::disk('public')->delete($bondRequest->supporting_document_path);
            }

            $attributes['supporting_document_path'] = $request->file('supporting_document')
                ->store('bond-documents', 'public');
        }

        if ($bondRequest === null) {
            $attributes['created_by'] = $request->user()->id;
            $attributes['status'] = BondRequestStatus::Pending;
        }

        return $attributes;
    }
}
