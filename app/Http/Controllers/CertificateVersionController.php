<?php

namespace App\Http\Controllers;

use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Services\ActivityLogger;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateVersionController extends Controller
{
    public function index(Request $request, BondRequest $bondRequest): JsonResponse
    {
        $this->authorize('view', $bondRequest);
        $this->authorize('viewCertificate', $bondRequest);

        $versions = $bondRequest->certificateVersions()
            ->with('generatedBy:id,name')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (CertificateVersion $version) => $this->versionPayload($version));

        return response()->json(['data' => $versions]);
    }

    public function view(Request $request, CertificateVersion $certificateVersion): BinaryFileResponse
    {
        $this->authorize('view', $certificateVersion);
        $certificateVersion->loadMissing('bondRequest');

        $relativePath = $certificateVersion->currentPdfPath();
        abort_if($relativePath === null, 404, 'No certificate file available for this version.');

        $absolutePath = storage_path('app/'.$relativePath);
        abort_unless(file_exists($absolutePath), 404, 'Confirmation file not found.');

        ActivityLogger::log(
            'certificate_version_viewed',
            "Confirmation version {$certificateVersion->version_number} viewed for bond request #{$certificateVersion->bond_request_id}.",
            $certificateVersion,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_viewed',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            entityId: $certificateVersion->id,
            description: "Confirmation version {$certificateVersion->version_number} viewed for bond request #{$certificateVersion->bond_request_id}.",
        );

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $filename = $this->downloadFilename($certificateVersion, $extension);
        $mimeType = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function download(Request $request, CertificateVersion $certificateVersion): BinaryFileResponse
    {
        $this->authorize('view', $certificateVersion);
        $certificateVersion->loadMissing('bondRequest');

        $relativePath = $certificateVersion->currentPdfPath();
        abort_if($relativePath === null, 404, 'No certificate file available for this version.');

        $absolutePath = storage_path('app/'.$relativePath);
        abort_unless(file_exists($absolutePath), 404, 'Confirmation file not found.');

        ActivityLogger::log(
            'certificate_version_downloaded',
            "Confirmation version {$certificateVersion->version_number} downloaded for bond request #{$certificateVersion->bond_request_id}.",
            $certificateVersion,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_downloaded',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            entityId: $certificateVersion->id,
            description: "Confirmation version {$certificateVersion->version_number} downloaded for bond request #{$certificateVersion->bond_request_id}.",
        );

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $filename = $this->downloadFilename($certificateVersion, $extension);

        return response()->download($absolutePath, $filename);
    }

    public function downloadDocx(Request $request, CertificateVersion $certificateVersion): BinaryFileResponse
    {
        $this->authorize('view', $certificateVersion);
        $certificateVersion->loadMissing('bondRequest');

        abort_if($certificateVersion->docx_path === null, 404, 'No DOCX available for this version.');

        $absolutePath = storage_path('app/'.$certificateVersion->docx_path);
        abort_unless(file_exists($absolutePath), 404, 'DOCX file not found.');

        ActivityLogger::log(
            'certificate_version_downloaded',
            "Confirmation version {$certificateVersion->version_number} DOCX downloaded for bond request #{$certificateVersion->bond_request_id}.",
            $certificateVersion,
            ['format' => 'docx'],
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_downloaded',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            entityId: $certificateVersion->id,
            description: "Confirmation version {$certificateVersion->version_number} DOCX downloaded for bond request #{$certificateVersion->bond_request_id}.",
        );

        $filename = $this->downloadFilename($certificateVersion, 'docx');

        return response()->download($absolutePath, $filename);
    }

    public function makeCurrent(Request $request, CertificateVersion $certificateVersion): RedirectResponse
    {
        $this->authorize('makeCurrent', $certificateVersion);
        $certificateVersion->loadMissing('bondRequest');
        $bondRequest = $certificateVersion->bondRequest;

        $currentPath = $certificateVersion->currentPdfPath();
        abort_if($currentPath === null, 404, 'No certificate file available for this version.');
        abort_unless(file_exists(storage_path('app/'.$currentPath)), 404, 'Confirmation file not found.');

        DB::transaction(function () use ($certificateVersion, $bondRequest, $currentPath): void {
            CertificateVersion::query()
                ->where('bond_request_id', $bondRequest->id)
                ->where('id', '!=', $certificateVersion->id)
                ->update(['is_current' => false]);

            $certificateVersion->update(['is_current' => true]);

            $bondRequest->update([
                'certificate_path' => $currentPath,
                'docx_path' => $certificateVersion->docx_path,
            ]);
        });

        ActivityLogger::log(
            'certificate_version_made_current',
            "Confirmation version {$certificateVersion->version_number} marked current for bond request #{$bondRequest->id}.",
            $certificateVersion,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'certificate_version_made_current',
            entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
            entityId: $certificateVersion->id,
            oldValues: ['is_current' => false],
            newValues: ['is_current' => true, 'bond_request_id' => $bondRequest->id],
            description: "Confirmation version {$certificateVersion->version_number} marked current for bond request #{$bondRequest->id}.",
        );

        return back()->with('success', 'Confirmation version marked as current.');
    }

    public function destroy(Request $request, CertificateVersion $certificateVersion): RedirectResponse
    {
        $this->authorize('delete', $certificateVersion);

        $versionNumber = $certificateVersion->version_number;
        $bondRequestId = $certificateVersion->bond_request_id;

        foreach ([$certificateVersion->docx_path, $certificateVersion->pdf_path] as $relativePath) {
            if (! filled($relativePath)) {
                continue;
            }

            $absolutePath = storage_path('app/'.$relativePath);

            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        ActivityLogger::log(
            'certificate_version_deleted',
            "Confirmation version {$versionNumber} deleted for bond request #{$bondRequestId}.",
            $certificateVersion,
        );

        $certificateVersion->delete();

        return back()->with('success', 'Confirmation version deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(CertificateVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'certificate_type' => $version->certificate_type?->value,
            'certificate_type_label' => $version->certificate_type_label,
            'generated_by' => $version->generatedBy?->only(['id', 'name']),
            'generated_at' => $version->generated_at?->toIso8601String(),
            'is_current' => $version->is_current,
            'has_pdf' => $version->pdf_path !== null,
            'has_docx' => filled($version->docx_path),
        ];
    }

    private function downloadFilename(CertificateVersion $version, string $extension): string
    {
        $bondRequest = $version->bondRequest;
        $obligee = trim((string) ($bondRequest->obligee_name ?? '')) ?: 'Confirmation';
        $bond = trim((string) ($bondRequest->bond_number ?? ''));
        $label = $bond !== '' ? "{$obligee} - {$bond}" : $obligee;

        return "{$label} v{$version->version_number}.{$extension}";
    }
}
