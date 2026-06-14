<?php

namespace App\Http\Controllers;

use App\Enums\CertificateTemplateType;
use App\Http\Requests\CertificateTemplate\StoreCertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\ActivityLogger;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        $archivedTemplates = CertificateTemplate::query()
            ->with('uploader:id,name')
            ->archived()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CertificateTemplates/Index', [
            'inUseTemplates' => CertificateTemplate::inUseSummaries(),
            'previousTemplates' => CertificateTemplate::query()
                ->with('uploader:id,name')
                ->previous()
                ->latest()
                ->get()
                ->map(fn (CertificateTemplate $template) => $template->toTableRow())
                ->values()
                ->all(),
            'archivedTemplates' => $archivedTemplates->through(
                fn (CertificateTemplate $template) => $template->toTableRow(),
            ),
            'canManage' => $request->user()->can('create', CertificateTemplate::class),
            'templateTypeOptions' => CertificateTemplateType::options(),
        ]);
    }

    public function store(StoreCertificateTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $type = CertificateTemplateType::from($validated['template_type']);
        $file = $request->file('template');
        $version = CertificateTemplate::nextVersion($type);

        $storedPath = $file->storeAs(
            'certificate-templates',
            sprintf('%s_v%d_%s.docx', $type->value, $version, Str::uuid()),
            'local',
        );

        $template = CertificateTemplate::create([
            'template_type' => $type,
            'template_name' => $validated['template_name'],
            'version' => $version,
            'file_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'is_active' => false,
        ]);

        ActivityLogger::log(
            'template_uploaded',
            "Certificate template {$template->template_name} ({$type->label()} v{$version}) uploaded.",
            $template,
            [
                'template_type' => $type->value,
                'version' => $version,
                'original_filename' => $template->original_filename,
            ],
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'template_uploaded',
            entityType: AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            entityId: $template->id,
            newValues: [
                'template_name' => $template->template_name,
                'template_type' => $type->value,
                'version' => $version,
            ],
            description: "Certificate template {$template->template_name} ({$type->label()} v{$version}) uploaded.",
        );

        return redirect()
            ->route('certificate-templates.index')
            ->with('success', 'Certificate template uploaded successfully.');
    }

    public function activate(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->authorize('activate', $certificateTemplate);

        abort_if($certificateTemplate->isArchived(), 422, 'Archived templates cannot be activated.');

        DB::transaction(function () use ($certificateTemplate): void {
            CertificateTemplate::query()
                ->where('template_type', $certificateTemplate->template_type)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $certificateTemplate->update(['is_active' => true]);
        });

        ActivityLogger::log(
            'template_activated',
            "Certificate template {$certificateTemplate->template_name} ({$certificateTemplate->template_type->label()} v{$certificateTemplate->version}) activated.",
            $certificateTemplate,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'template_activated',
            entityType: AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            entityId: $certificateTemplate->id,
            oldValues: ['is_active' => false],
            newValues: ['is_active' => true],
            description: "Certificate template {$certificateTemplate->template_name} activated.",
        );

        return back()->with('success', 'Certificate template activated.');
    }

    public function archive(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->authorize('archive', $certificateTemplate);

        abort_if($certificateTemplate->isArchived(), 422, 'This template is already archived.');

        $certificateTemplate->update([
            'is_active' => false,
            'archived_at' => now(),
        ]);

        ActivityLogger::log(
            'template_archived',
            "Certificate template {$certificateTemplate->template_name} ({$certificateTemplate->template_type->label()} v{$certificateTemplate->version}) archived.",
            $certificateTemplate,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'template_archived',
            entityType: AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            entityId: $certificateTemplate->id,
            oldValues: ['archived_at' => null],
            newValues: ['archived_at' => $certificateTemplate->archived_at?->toIso8601String()],
            description: "Certificate template {$certificateTemplate->template_name} archived.",
        );

        return back()->with('success', 'Certificate template archived.');
    }

    public function download(Request $request, CertificateTemplate $certificateTemplate): BinaryFileResponse
    {
        $this->authorize('download', $certificateTemplate);

        $absolutePath = $certificateTemplate->absolutePath();
        abort_unless(file_exists($absolutePath), 404, 'Template file not found.');

        ActivityLogger::log(
            'template_downloaded',
            "Certificate template {$certificateTemplate->template_name} (v{$certificateTemplate->version}) downloaded.",
            $certificateTemplate,
        );

        AuditLogService::log(
            user: $request->user(),
            action: 'template_downloaded',
            entityType: AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            entityId: $certificateTemplate->id,
            description: "Certificate template {$certificateTemplate->template_name} downloaded.",
        );

        return response()->download($absolutePath, $certificateTemplate->original_filename);
    }

    public function downloadFallback(Request $request, string $type): BinaryFileResponse
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        $templateType = CertificateTemplateType::from($type);
        $path = CertificateTemplate::fallbackPath($templateType);
        abort_unless(file_exists($path), 404, 'Fallback template file not found.');

        return response()->download($path, CertificateTemplate::fallbackFilename($templateType));
    }
}
