<?php

namespace App\Services;

use App\Enums\CertificateTemplateType;
use App\Models\BondRequest;
use App\Models\CertificateTemplate;
use App\Models\CertificateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Generates DOCX and PDF certificates from bond request data.
 *
 * Flow:
 *   1. Detect certificate type → choose correct template.
 *   2. TemplateNormalizerService merges split-run placeholders and converts
 *      [[placeholder]] → ${placeholder}.
 *   3. TemplateDataBuilder builds the full placeholder map
 *      (text values + image values).
 *   4. PHPWord TemplateProcessor applies setValue() for text and
 *      setImageValue() for images.
 *   5. The filled DOCX is saved to storage/app/private/generated-docx/.
 *   6. LibreOffice headless converts the DOCX to PDF stored in
 *      storage/app/private/certificates/.
 *   7. A certificate_versions record is created and marked current; certificate_path
 *      and docx_path on the BondRequest point to the current version.
 */
class CertificateGenerationService
{
    public function __construct(
        private readonly TemplateNormalizerService $normalizer,
        private readonly TemplateDataBuilder $dataBuilder,
        private readonly PlaceholderRenderer $placeholderRenderer,
        private readonly DocxEndorsementSpacingNormalizer $endorsementSpacingNormalizer,
    ) {}

    /**
     * @throws RuntimeException when generation fails.
     */
    public function generate(BondRequest $bondRequest, User $generatedBy): void
    {
        $this->assertEndorsementIsValid($bondRequest);

        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch', 'bondTypeMaster']);

        $versionNumber = $this->nextVersionNumber($bondRequest);
        $templateId = $this->resolveTemplateId($bondRequest);

        $templatePath = $this->templatePath($bondRequest);
        $normalizedPath = $this->normalizer->normalize($templatePath);

        try {
            $data = $this->dataBuilder->build($bondRequest);
            $renderedText = $this->placeholderRenderer->render($data['text']);
            $processor = new TemplateProcessor($normalizedPath);

            $this->applyTextValues($processor, $renderedText);
            $this->applyImageValues($processor, $data['images'], $bondRequest);

            $docxPath = $this->saveDocx($processor, $bondRequest, $versionNumber);

            if (! $bondRequest->include_endorsement_number) {
                $this->endorsementSpacingNormalizer->normalize(storage_path("app/{$docxPath}"));
            }

            $pdfPath = $this->convertToPdf($docxPath, $bondRequest, $versionNumber);
            $currentPath = $pdfPath ?? $docxPath;

            DB::transaction(function () use (
                $bondRequest,
                $generatedBy,
                $versionNumber,
                $templateId,
                $docxPath,
                $pdfPath,
                $currentPath,
            ): void {
                CertificateVersion::query()
                    ->where('bond_request_id', $bondRequest->id)
                    ->update(['is_current' => false]);

                $version = CertificateVersion::create([
                    'bond_request_id' => $bondRequest->id,
                    'version_number' => $versionNumber,
                    'certificate_type' => $bondRequest->certificate_type,
                    'template_id' => $templateId,
                    'docx_path' => $docxPath,
                    'pdf_path' => $pdfPath,
                    'generated_by' => $generatedBy->id,
                    'generated_at' => now(),
                    'is_current' => true,
                ]);

                $bondRequest->update([
                    'docx_path' => $docxPath,
                    'certificate_path' => $currentPath,
                ]);

                ActivityLogger::log(
                    'certificate_version_created',
                    "Certificate version {$versionNumber} created for bond request #{$bondRequest->id}.",
                    $version,
                    [
                        'bond_request_id' => $bondRequest->id,
                        'version_number' => $versionNumber,
                    ],
                );

                AuditLogService::log(
                    user: $generatedBy,
                    action: 'certificate_version_created',
                    entityType: AuditLogService::ENTITY_CERTIFICATE_VERSION,
                    entityId: $version->id,
                    newValues: [
                        'bond_request_id' => $bondRequest->id,
                        'version_number' => $versionNumber,
                    ],
                    description: "Certificate version {$versionNumber} created for bond request #{$bondRequest->id}.",
                );
            });
        } finally {
            if (file_exists($normalizedPath)) {
                @unlink($normalizedPath);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function assertEndorsementIsValid(BondRequest $bondRequest): void
    {
        if ($bondRequest->include_endorsement_number && blank($bondRequest->endorsement_number)) {
            throw new RuntimeException('Endorsement number is required when include endorsement number is enabled.');
        }
    }

    // -------------------------------------------------------------------------
    // Template selection
    // -------------------------------------------------------------------------

    private function templatePath(BondRequest $bondRequest): string
    {
        $type = CertificateTemplateType::fromCertificateType($bondRequest->certificate_type);
        $activeTemplate = CertificateTemplate::activeForType($type);

        if ($activeTemplate !== null) {
            $absolutePath = $activeTemplate->absolutePath();

            if (file_exists($absolutePath)) {
                return $absolutePath;
            }
        }

        $filename = CertificateTemplate::fallbackFilename($type);

        $path = CertificateTemplate::fallbackPath($type);

        if (! file_exists($path)) {
            throw new RuntimeException("Certificate template not found: {$path}");
        }

        return $path;
    }

    // -------------------------------------------------------------------------
    // Placeholder replacement
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $values
     */
    private function applyTextValues(TemplateProcessor $processor, array $values): void
    {
        foreach ($values as $placeholder => $value) {
            $processor->setValue(
                $placeholder,
                htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            );
        }
    }

    /**
     * @param  array<string, array{path: string, width: int, height: int, ratio: bool}>  $images
     */
    private function applyImageValues(TemplateProcessor $processor, array $images, BondRequest $bondRequest): void
    {
        foreach ($images as $placeholder => $imageData) {
            try {
                $processor->setImageValue($placeholder, $imageData);
            } catch (\Throwable $e) {
                // If the template doesn't have a proper drawing placeholder for this
                // macro, PHPWord may throw or silently skip. Fall back to empty text.
                Log::warning(
                    "Bond request #{$bondRequest->id}: setImageValue('{$placeholder}') failed — {$e->getMessage()}. Using empty text fallback."
                );
                $processor->setValue($placeholder, '');
            }
        }
    }

    // -------------------------------------------------------------------------
    // File storage
    // -------------------------------------------------------------------------

    private function saveDocx(TemplateProcessor $processor, BondRequest $bondRequest, int $versionNumber): string
    {
        $relativePath = $this->versionedRelativePath($bondRequest, $versionNumber, 'docx');
        $fullPath = storage_path("app/{$relativePath}");
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $processor->saveAs($fullPath);

        return $relativePath;
    }

    private function convertToPdf(string $docxRelativePath, BondRequest $bondRequest, int $versionNumber): ?string
    {
        $pdfRelativePath = $this->versionedRelativePath($bondRequest, $versionNumber, 'pdf');
        $directory = dirname(storage_path("app/{$pdfRelativePath}"));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $docxAbsolutePath = storage_path("app/{$docxRelativePath}");
        $pdfAbsolutePath = storage_path("app/{$pdfRelativePath}");

        $libreOffice = $this->findLibreOffice();

        if ($libreOffice === null) {
            return null;
        }

        $escapedExe = escapeshellarg($libreOffice);
        $escapedDocx = escapeshellarg($docxAbsolutePath);
        $escapedDir = escapeshellarg($directory);

        $command = "{$escapedExe} --headless --convert-to pdf --outdir {$escapedDir} {$escapedDocx} 2>&1";
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        $libreOfficeOutput = $directory.'/'.pathinfo($docxAbsolutePath, PATHINFO_FILENAME).'.pdf';

        if (file_exists($libreOfficeOutput)) {
            rename($libreOfficeOutput, $pdfAbsolutePath);

            return $pdfRelativePath;
        }

        return null;
    }

    private function versionedRelativePath(BondRequest $bondRequest, int $versionNumber, string $extension): string
    {
        $now = now();
        $year = $now->format('Y');
        $month = $now->format('m');
        $filename = "request_{$bondRequest->id}_v{$versionNumber}.{$extension}";

        if ($extension === 'docx') {
            return "private/generated-docx/{$year}/{$month}/{$filename}";
        }

        return "private/certificates/{$year}/{$month}/{$filename}";
    }

    private function nextVersionNumber(BondRequest $bondRequest): int
    {
        $max = CertificateVersion::query()
            ->where('bond_request_id', $bondRequest->id)
            ->max('version_number');

        return ((int) $max) + 1;
    }

    private function resolveTemplateId(BondRequest $bondRequest): ?int
    {
        $type = CertificateTemplateType::fromCertificateType($bondRequest->certificate_type);

        return CertificateTemplate::activeForType($type)?->id;
    }

    private function findLibreOffice(): ?string
    {
        $candidates = [
            'libreoffice',
            'soffice',
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/usr/local/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }

            if (! str_contains($candidate, DIRECTORY_SEPARATOR) && ! str_contains($candidate, '/')) {
                $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
                exec("{$which} ".escapeshellarg($candidate).' 2>&1', $out, $code);

                if ($code === 0 && ! empty($out[0])) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
