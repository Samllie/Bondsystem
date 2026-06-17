<?php

namespace App\Http\Controllers;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateType;
use App\Enums\PartyType;
use App\Enums\RoleSlug;
use App\Http\Requests\BondRequest\ApproveBondRequestRequest;
use App\Http\Requests\BondRequest\StoreBondRequestRequest;
use App\Http\Requests\BondRequest\UpdateBondRequestRequest;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AuditLogService;
use App\Services\BondRequestSupportingDocumentService;
use App\Services\CertificateGenerationService;
use App\Services\KycObligeeService;
use App\Services\NotaryFeeService;
use App\Services\NotificationService;
use App\Support\AmountInWords;
use App\Support\BondNumberGenerator;
use App\Support\BranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BondRequestController extends Controller
{
    public function __construct(
        private KycObligeeService $kycObligeeService,
        private CertificateGenerationService $certificateGenerationService,
        private NotaryFeeService $notaryFeeService,
        private NotificationService $notificationService,
        private BondRequestSupportingDocumentService $supportingDocumentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BondRequest::class);

        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: null;

        $query = BondRequest::query()
            ->with(['principal:id,company_name', 'creator:id,name', 'approver:id,name']);

        if ($user->hasRole(RoleSlug::Requester)) {
            $query->where('created_by', $user->id);
        } else {
            BranchScope::applyBondCreatorScope($query, $user, $branchId);
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
            $summary = $bondRequest->obligeeSummary();
            $bondRequest->setRelation('obligee', $summary ? new Fluent($summary) : null);

            return $bondRequest;
        });

        return Inertia::render('BondRequests/Index', [
            'bondRequests' => $bondRequests,
            'filters' => $request->only(['search', 'status', 'bond_type_id', 'branch_id']),
            'statusOptions' => BondRequestStatus::options(),
            'bondTypeOptions' => $this->bondTypeOptions(),
            'branchOptions' => BranchScope::branchOptions($user),
            'showBranchFilter' => BranchScope::showBranchFilter($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', BondRequest::class);

        $user = $request->user()->load('branch');

        return Inertia::render('BondRequests/Form', [
            'bondRequest' => null,
            'selectedPrincipal' => null,
            'selectedObligee' => null,
            'bondTypeOptions' => $this->bondTypeOptions(),
            'certificateTypeOptions' => CertificateType::options(),
            'partyTypeOptions' => PartyType::options(),
            'requesterBranchCode' => BondNumberGenerator::branchCodeFor($user),
            'branchFund' => $this->branchFundProps($user),
        ]);
    }

    public function store(StoreBondRequestRequest $request): RedirectResponse
    {
        $bondRequest = BondRequest::create(
            $this->bondRequestAttributes($request)
        );

        $paths = $this->supportingDocumentService->syncFromRequest($request, $bondRequest);
        $bondRequest->update([
            'supporting_document_paths' => $paths === [] ? null : $paths,
        ]);

        ActivityLogger::log('created', "Bond request {$bondRequest->bond_number} created.", $bondRequest);
        AuditLogService::log(
            user: $request->user(),
            action: 'bond_request_created',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            newValues: [
                'bond_number' => $bondRequest->bond_number,
                'status' => $bondRequest->status->value,
            ],
            description: "Bond request {$bondRequest->bond_number} created.",
        );
        AuditLogService::log(
            user: $request->user(),
            action: 'bond_request_submitted',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            newValues: [
                'bond_number' => $bondRequest->bond_number,
                'status' => $bondRequest->status->value,
            ],
            description: "Bond request {$bondRequest->bond_number} submitted.",
        );
        $this->notificationService->bondRequestSubmitted($bondRequest);

        return redirect()->route('bond-requests.show', $bondRequest)
            ->with('success', 'Bond request created successfully.');
    }

    public function show(BondRequest $bondRequest): Response
    {
        $this->authorize('view', $bondRequest);

        $bondRequest->load(['principal', 'bondTypeMaster', 'signatory', 'notary', 'creator:id,name,branch_id,branch_code', 'creator.branch', 'approver:id,name']);
        $summary = $bondRequest->obligeeSummary();
        $bondRequest->setRelation('obligee', $summary ? new Fluent($summary) : null);
        $bondRequest->append(['status_label', 'status_color', 'bond_type_label', 'certificate_type_label', 'bond_label']);

        $canApprove = request()->user()->hasPermission('bond-requests.approve')
            && $bondRequest->status === BondRequestStatus::Pending;

        $canGenerateCertificate = request()->user()->hasPermission('bond-requests.approve')
            && in_array($bondRequest->status->value, [BondRequestStatus::Approved->value, BondRequestStatus::Notarized->value], true);

        $needsOptions = $canApprove || $canGenerateCertificate;

        $user = request()->user();
        $canMakeVersionCurrent = $user->hasRole(RoleSlug::SuperAdmin)
            || $user->hasPermission('users.view');
        $canDeleteCertificateVersion = $user->hasRole(RoleSlug::SuperAdmin)
            || $user->hasPermission('bond-requests.approve');

        return Inertia::render('BondRequests/Show', [
            'bondRequest' => $bondRequest,
            'supportingDocuments' => $this->supportingDocumentService->documentsFor($bondRequest),
            'canUpdate' => request()->user()->can('update', $bondRequest),
            'canDelete' => request()->user()->can('delete', $bondRequest),
            'canApprove' => $canApprove,
            'canNotarize' => request()->user()->hasPermission('bond-requests.notarize')
                && $bondRequest->status === BondRequestStatus::Approved,
            'canGenerateCertificate' => $canGenerateCertificate,
            'hasCertificate' => $bondRequest->certificate_path !== null,
            'hasDocx' => $bondRequest->docx_path !== null,
            'canMakeVersionCurrent' => $canMakeVersionCurrent,
            'canDeleteCertificateVersion' => $canDeleteCertificateVersion,
            'showVersionGeneratedBy' => ! $user->hasRole(RoleSlug::Requester),
            'certificateVersions' => $bondRequest->certificateVersions()
                ->with('generatedBy:id,name')
                ->get()
                ->map(fn (CertificateVersion $version) => [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'certificate_type' => $version->certificate_type?->value,
                    'certificate_type_label' => $version->certificate_type_label,
                    'generated_by' => $version->generatedBy?->only(['id', 'name']),
                    'generated_at' => $version->generated_at?->toIso8601String(),
                    'is_current' => $version->is_current,
                    'has_pdf' => $version->pdf_path !== null,
                    'has_docx' => filled($version->docx_path),
                ]),
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
            'partyTypeOptions' => PartyType::options(),
            'supportingDocuments' => $this->supportingDocumentService->documentsFor($bondRequest),
            'requesterBranchCode' => BondNumberGenerator::branchCodeFor($request->user()->load('branch')),
        ]);
    }

    public function update(UpdateBondRequestRequest $request, BondRequest $bondRequest): RedirectResponse
    {
        $oldValues = [
            'status' => $bondRequest->status->value,
            'amount' => (string) $bondRequest->amount,
            'bond_number' => $bondRequest->bond_number,
        ];

        $bondRequest->update(
            $this->bondRequestAttributes($request, $bondRequest)
        );

        $paths = $this->supportingDocumentService->syncFromRequest($request, $bondRequest);
        $bondRequest->update([
            'supporting_document_paths' => $paths === [] ? null : $paths,
        ]);

        ActivityLogger::log('updated', "Bond request {$bondRequest->bond_number} updated.", $bondRequest);
        AuditLogService::log(
            user: $request->user(),
            action: 'bond_request_updated',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            oldValues: $oldValues,
            newValues: [
                'status' => $bondRequest->status->value,
                'amount' => (string) $bondRequest->amount,
                'bond_number' => $bondRequest->bond_number,
            ],
            description: "Bond request {$bondRequest->bond_number} updated.",
        );

        return redirect()->route('bond-requests.show', $bondRequest)
            ->with('success', 'Bond request updated successfully.');
    }

    public function destroy(BondRequest $bondRequest): RedirectResponse
    {
        $this->authorize('delete', $bondRequest);

        $number = $bondRequest->bond_number;
        $this->supportingDocumentService->deleteAll($bondRequest->supporting_document_paths ?? []);
        $bondRequest->delete();

        ActivityLogger::log('deleted', "Bond request {$number} deleted.", $bondRequest);

        return redirect()->route('bond-requests.index')
            ->with('success', 'Bond request deleted successfully.');
    }

    public function approve(ApproveBondRequestRequest $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($bondRequest->status === BondRequestStatus::Pending, 422);

        DB::transaction(function () use ($request, $bondRequest): void {
            $signatory = $request->filled('signatory_id')
                ? Signatory::query()->findOrFail($request->integer('signatory_id'))
                : null;

            $bondRequest->update([
                'status' => BondRequestStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'signatory_id' => $signatory?->id,
                'signatory_position' => $signatory?->position,
                'include_signatory_signature' => $signatory !== null && $request->boolean('include_signatory_signature'),
                'notary_id' => $request->filled('notary_id') ? $request->integer('notary_id') : null,
                'doc_no' => $request->input('doc_no'),
                'page_no' => $request->input('page_no'),
                'book_no' => $request->input('book_no'),
                'series_year' => $request->input('series_year'),
                'tin' => $signatory?->tin,
            ]);

            $bondRequest->loadMissing('creator');
        });

        ActivityLogger::log('approved', "Bond request {$bondRequest->bond_number} approved.", $bondRequest);
        AuditLogService::log(
            user: $request->user(),
            action: 'bond_request_approved',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            oldValues: ['status' => BondRequestStatus::Pending->value],
            newValues: ['status' => BondRequestStatus::Approved->value],
            description: "Bond request {$bondRequest->bond_number} approved.",
        );
        $this->notificationService->bondRequestApproved($bondRequest);

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
        AuditLogService::log(
            user: $request->user(),
            action: 'bond_request_rejected',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            oldValues: ['status' => BondRequestStatus::Pending->value],
            newValues: ['status' => BondRequestStatus::Rejected->value],
            description: "Bond request {$bondRequest->bond_number} rejected.",
        );
        $this->notificationService->bondRequestRejected($bondRequest);

        return back()->with('success', 'Bond request rejected.');
    }

    public function notarize(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.notarize'), 403);
        abort_unless($bondRequest->status === BondRequestStatus::Approved, 422);

        $bondRequest->update(['status' => BondRequestStatus::Notarized]);

        ActivityLogger::log('notarized', "Bond request {$bondRequest->bond_number} notarized.", $bondRequest);
        $this->notificationService->bondRequestNotarized($bondRequest);

        return back()->with('success', 'Bond request marked as notarized.');
    }

    public function generateCertificate(Request $request, BondRequest $bondRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('bond-requests.approve'), 403);
        abort_unless(
            in_array($bondRequest->status->value, [BondRequestStatus::Approved->value, BondRequestStatus::Notarized->value], true),
            422,
            'Confirmation can only be generated for approved or notarized bond requests.',
        );

        if ($bondRequest->include_endorsement_number && blank($bondRequest->endorsement_number)) {
            return back()->withErrors([
                'endorsement_number' => 'Endorsement number is required when include endorsement number is enabled.',
            ]);
        }

        $validated = $request->validate([
            'signatory_id' => ['nullable', 'integer', 'exists:signatories,id'],
            'include_signatory_signature' => ['sometimes', 'boolean'],
            'notary_id' => ['nullable', 'integer', 'exists:notaries,id'],
            'doc_no' => ['nullable', 'string', 'max:50'],
            'page_no' => ['nullable', 'string', 'max:50'],
            'book_no' => ['nullable', 'string', 'max:50'],
            'series_year' => ['nullable', 'string', 'size:4'],
        ]);

        $signatory = $request->has('signatory_id')
            ? (filled($validated['signatory_id'] ?? null)
                ? Signatory::findOrFail($validated['signatory_id'])
                : null)
            : ($bondRequest->signatory_id ? $bondRequest->signatory : null);

        try {
            DB::transaction(function () use ($request, $bondRequest, $validated, $signatory): void {
                $bondRequest->update([
                    'signatory_id' => $signatory?->id,
                    'signatory_position' => $signatory?->position ?? $bondRequest->signatory_position,
                    'include_signatory_signature' => $signatory !== null && $request->boolean('include_signatory_signature'),
                    'notary_id' => $request->has('notary_id')
                        ? ($validated['notary_id'] ?? null)
                        : $bondRequest->notary_id,
                    'doc_no' => $request->has('doc_no') ? ($validated['doc_no'] ?? null) : $bondRequest->doc_no,
                    'page_no' => $request->has('page_no') ? ($validated['page_no'] ?? null) : $bondRequest->page_no,
                    'book_no' => $request->has('book_no') ? ($validated['book_no'] ?? null) : $bondRequest->book_no,
                    'series_year' => $request->has('series_year') ? ($validated['series_year'] ?? null) : $bondRequest->series_year,
                    'tin' => $signatory?->tin ?? $bondRequest->tin,
                ]);

                $bondRequest->refresh()->loadMissing(['creator', 'signatory', 'notary', 'principal']);

                if ($bondRequest->notary_id !== null) {
                    $this->notaryFeeService->chargeWhenNotarySelected($bondRequest->creator, $bondRequest);
                }

                $this->certificateGenerationService->generate($bondRequest, $request->user());
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['notary_id' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error("Certificate generation failed for bond request #{$bondRequest->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return back()->with('error', 'Confirmation generation failed: '.$e->getMessage());
        }

        ActivityLogger::log('generated', "Confirmation generated for bond request {$bondRequest->bond_number}.", $bondRequest);

        $bondRequest->refresh();
        $version = $bondRequest->certificateVersions()->latest('version_number')->first();
        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_generated',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            oldValues: ['certificate_path' => null],
            newValues: [
                'certificate_path' => $bondRequest->certificate_path,
                'version_number' => $version?->version_number,
            ],
            description: $version
                ? "Generated bond confirmation version {$version->version_number} for {$bondRequest->bond_number}."
                : "Generated bond confirmation for {$bondRequest->bond_number}.",
        );

        $this->notificationService->certificateGenerated($bondRequest);

        return back()->with('success', 'Confirmation generated successfully.');
    }

    public function viewCertificate(Request $request, BondRequest $bondRequest): BinaryFileResponse
    {
        $this->authorize('viewCertificate', $bondRequest);
        abort_if($bondRequest->certificate_path === null, 404, 'No confirmation has been generated yet.');

        $absolutePath = storage_path('app/'.$bondRequest->certificate_path);
        abort_unless(file_exists($absolutePath), 404, 'Confirmation file not found.');

        $extension = pathinfo($bondRequest->certificate_path, PATHINFO_EXTENSION);
        $filename = $this->certificateFilename($bondRequest, $extension);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_viewed',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            description: "Confirmation viewed for bond request {$bondRequest->bond_number}.",
        );

        return response()->file(
            $absolutePath,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ],
        );
    }

    public function downloadCertificate(Request $request, BondRequest $bondRequest): BinaryFileResponse
    {
        $this->authorize('viewCertificate', $bondRequest);
        abort_if($bondRequest->certificate_path === null, 404, 'No confirmation has been generated yet.');

        $absolutePath = storage_path('app/'.$bondRequest->certificate_path);
        abort_unless(file_exists($absolutePath), 404, 'Confirmation file not found.');

        $extension = pathinfo($bondRequest->certificate_path, PATHINFO_EXTENSION);
        $filename = $this->certificateFilename($bondRequest, $extension);

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_downloaded',
            entityType: AuditLogService::ENTITY_BOND_REQUEST,
            entityId: $bondRequest->id,
            description: "Confirmation downloaded for bond request {$bondRequest->bond_number}.",
        );

        return response()->download($absolutePath, $filename);
    }

    public function downloadDocx(Request $request, BondRequest $bondRequest): BinaryFileResponse
    {
        $this->authorize('viewCertificate', $bondRequest);
        abort_if($bondRequest->docx_path === null, 404, 'No DOCX has been generated yet.');

        $absolutePath = storage_path('app/'.$bondRequest->docx_path);
        abort_unless(file_exists($absolutePath), 404, 'DOCX file not found.');

        $filename = $this->certificateFilename($bondRequest, 'docx');

        return response()->download($absolutePath, $filename);
    }

    public function downloadSupportingDocument(Request $request, BondRequest $bondRequest): BinaryFileResponse
    {
        $this->authorize('view', $bondRequest);

        $path = $request->query('path');
        abort_unless(
            is_string($path) && in_array($path, $bondRequest->supporting_document_paths ?? [], true),
            404,
            'Supporting document not found.',
        );

        $absolutePath = $this->supportingDocumentService->absolutePath($path);
        abort_if($absolutePath === null, 404, 'Supporting document file not found.');

        return response()->download($absolutePath, basename($path));
    }

    /**
     * Build a human-readable download filename: "{Obligee} - {Bond}.{ext}".
     */
    private function certificateFilename(BondRequest $bondRequest, string $extension): string
    {
        $obligee = trim((string) ($bondRequest->obligee_name ?? '')) ?: 'Confirmation';
        $bond = trim((string) ($bondRequest->bond_number ?? ''));
        $name = $bond !== '' ? "{$obligee} - {$bond}" : $obligee;

        // Strip characters that are invalid in filenames while keeping it readable.
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        return "{$name}.{$extension}";
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
            ->get(['id', 'name', 'position', 'signature_path'])
            ->map(fn (Signatory $signatory) => [
                'value' => $signatory->id,
                'label' => $signatory->name,
                'position' => $signatory->position,
                'signature_url' => $signatory->signature_url,
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
        $obligeeId = $request->filled('obligee_id') ? $request->integer('obligee_id') : null;
        $obligee = $obligeeId !== null ? $this->kycObligeeService->find($obligeeId) : null;
        $obligeeName = $request->string('obligee_name')->trim()->toString();
        $principalName = $request->string('principal_name')->trim()->toString();
        $certificateType = $request->enum('certificate_type', CertificateType::class);

        $attributes = [
            ...$request->validated(),
            'obligee_id' => $obligeeId,
            'obligee_name' => $obligeeName !== '' ? $obligeeName : ($obligee['company_name'] ?? ''),
            'principal_id' => $request->filled('principal_id') ? $request->integer('principal_id') : null,
            'principal_name' => $principalName,
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

        $attributes['party_type'] = $request->enum('party_type', PartyType::class) ?? PartyType::Private;
        $attributes['include_endorsement_number'] = $request->boolean('include_endorsement_number');
        $attributes['endorsement_number'] = $request->boolean('include_endorsement_number')
            ? $request->string('endorsement_number')->trim()->toString()
            : null;

        unset($attributes['supporting_documents'], $attributes['removed_supporting_documents']);

        if ($bondRequest === null) {
            $attributes['created_by'] = $request->user()->id;
            $attributes['status'] = BondRequestStatus::Pending;
        }

        return $attributes;
    }

    /**
     * @return array{balance: float, minimumBalance: float, canSubmit: bool, branchName: string|null}
     */
    private function branchFundProps(User $user): array
    {
        $branch = $user->branch;
        $balance = $user->branchBalance();
        $minimumBalance = $branch?->minimumBalance() ?? 1000.0;

        return [
            'balance' => $balance,
            'minimumBalance' => $minimumBalance,
            'canSubmit' => $branch !== null && $branch->meetsMinimumBalanceForSubmission(),
            'branchName' => $branch?->name,
        ];
    }
}
