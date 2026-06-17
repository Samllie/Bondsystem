<?php

namespace App\Http\Controllers;

use App\Models\CertificateVersion;
use App\Services\AuditLogService;
use App\Services\ConfirmationNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateVerificationController extends Controller
{
    public function __construct(
        private readonly ConfirmationNumberService $confirmationNumberService,
    ) {}

    public function search(): Response
    {
        return Inertia::render('CertificateVerification/Search');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation_number' => ['required', 'string', 'max:255'],
        ]);

        AuditLogService::log(
            user: null,
            action: 'confirmation_number_searched',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            newValues: [
                'confirmation_number' => $validated['confirmation_number'],
            ],
            description: 'Confirmation number searched on public verification page.',
        );

        $version = $this->confirmationNumberService->findVersionByLookup(
            $validated['confirmation_number'],
        );

        if ($version === null) {
            return redirect()
                ->route('certificate-verification.search')
                ->withErrors([
                    'confirmation_number' => 'No confirmation found for that confirmation number.',
                ]);
        }

        return redirect()->route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]);
    }

    public function show(string $verificationToken): Response
    {
        $version = CertificateVersion::query()
            ->with(['bondRequest' => fn ($query) => $query->withTrashed()->with('principal')])
            ->where('verification_token', $verificationToken)
            ->first();

        if ($version === null) {
            AuditLogService::log(
                user: null,
                action: 'certificate_verification_failed',
                entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
                description: 'Public confirmation verification failed for an unknown token.',
            );

            return Inertia::render('CertificateVerification/Show', [
                'valid' => false,
            ]);
        }

        $version->increment('verification_count');
        $version->update(['last_verified_at' => now()]);

        AuditLogService::log(
            user: null,
            action: 'certificate_verified',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            entityId: $version->id,
            newValues: [
                'confirmation_number' => $version->confirmation_number,
                'verification_count' => $version->verification_count,
            ],
            description: 'Public confirmation verification succeeded.',
        );

        $bondRequest = $version->bondRequest;

        if ($bondRequest === null) {
            AuditLogService::log(
                user: null,
                action: 'certificate_verification_failed',
                entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
                entityId: $version->id,
                description: 'Public confirmation verification failed because the linked bond request is unavailable.',
            );

            return Inertia::render('CertificateVerification/Show', [
                'valid' => false,
            ]);
        }

        $currentVersionNumber = null;

        if (! $version->is_current) {
            $currentVersionNumber = CertificateVersion::query()
                ->where('bond_request_id', $version->bond_request_id)
                ->where('is_current', true)
                ->value('version_number');
        }

        return Inertia::render('CertificateVerification/Show', [
            'valid' => true,
            'status' => $version->is_current ? 'CURRENT' : 'ARCHIVED',
            'confirmationNumber' => $version->confirmation_number,
            'certificateType' => $version->certificate_type_label,
            'bondRequestReference' => $bondRequest->bond_label,
            'principal' => $bondRequest->principal?->company_name ?? $bondRequest->principal_name ?? '—',
            'obligee' => $bondRequest->obligee_name ?? '—',
            'amount' => $bondRequest->amount !== null
                ? number_format((float) $bondRequest->amount, 2)
                : '—',
            'dateIssued' => optional($bondRequest->date_issued)->toDateString(),
            'expiryDate' => optional($bondRequest->expiry_date)->toDateString(),
            'versionNumber' => $version->version_number,
            'generatedDate' => optional($version->generated_at)?->timezone(config('app.timezone'))->format('M d, Y g:i A'),
            'currentVersionNumber' => $currentVersionNumber,
        ]);
    }
}
