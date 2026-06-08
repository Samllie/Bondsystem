<?php

namespace App\Services;

use App\Enums\CertificateTemplateType;
use App\Models\BondRequest;
use App\Models\CertificateTemplate;
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
 *   7. certificate_path and docx_path are persisted on the BondRequest.
 */
class CertificateGenerationService
{
    public function __construct(
        private readonly TemplateNormalizerService $normalizer,
        private readonly TemplateDataBuilder $dataBuilder,
    ) {}

    /**
     * @throws RuntimeException when generation fails.
     */
    public function generate(BondRequest $bondRequest): void
    {
        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        $templatePath = $this->templatePath($bondRequest);
        $normalizedPath = $this->normalizer->normalize($templatePath);

        try {
            $data = $this->dataBuilder->build($bondRequest);
            $processor = new TemplateProcessor($normalizedPath);

            $this->applyTextValues($processor, $data['text']);
            $this->applyImageValues($processor, $data['images'], $bondRequest);

            $docxPath = $this->saveDocx($processor, $bondRequest);
            $pdfPath = $this->convertToPdf($docxPath, $bondRequest);

            $bondRequest->update([
                'docx_path' => $docxPath,
                'certificate_path' => $pdfPath ?? $docxPath,
            ]);
        } finally {
            if (file_exists($normalizedPath)) {
                @unlink($normalizedPath);
            }
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

    private function saveDocx(TemplateProcessor $processor, BondRequest $bondRequest): string
    {
        $directory = storage_path('app/private/generated-docx');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $this->buildFilename($bondRequest, 'docx');
        $fullPath = "{$directory}/{$filename}";

        $processor->saveAs($fullPath);

        return "private/generated-docx/{$filename}";
    }

    private function convertToPdf(string $docxRelativePath, BondRequest $bondRequest): ?string
    {
        $directory = storage_path('app/private/certificates');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $docxAbsolutePath = storage_path("app/{$docxRelativePath}");
        $pdfFilename = $this->buildFilename($bondRequest, 'pdf');

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
            $targetPath = "{$directory}/{$pdfFilename}";
            rename($libreOfficeOutput, $targetPath);

            return "private/certificates/{$pdfFilename}";
        }

        return null;
    }

    private function buildFilename(BondRequest $bondRequest, string $extension): string
    {
        $obligee = $bondRequest->obligee_name ?? 'obligee';
        $bond = $bondRequest->bond_number ?? 'bond';
        $base = preg_replace('/[^A-Za-z0-9\-_]+/', '_', "{$obligee}_{$bond}");
        $base = trim((string) $base, '_');

        // The id keeps the stored file unique (bond numbers can repeat across requests).
        return "{$base}_{$bondRequest->id}.{$extension}";
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
