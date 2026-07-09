<?php

namespace App\Services;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Support\DateFormatter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds the complete placeholder data set for a certificate template.
 *
 * Separates text replacements from image replacements so that the caller can
 * route each to the correct PHPWord TemplateProcessor method:
 *   - text   → TemplateProcessor::setValue()
 *   - images → TemplateProcessor::setImageValue()
 *
 * Expected relations on the BondRequest before calling build():
 *   principal, signatory, notary, creator.branch
 *
 * Return shape:
 * [
 *   'text'   => ['Placeholder Name' => 'value', ...],
 *   'images' => ['Placeholder Name' => ['path'=>..,'width'=>..,'height'=>..,'ratio'=>..], ...]
 * ]
 */
class TemplateDataBuilder
{
    public function __construct(
        private readonly AmountToWordsService $amountToWords,
    ) {}

    /**
     * Build the full placeholder map for the given BondRequest.
     *
     * @return array{
     *   text: array<string, string>,
     *   images: array<string, array{path: string, width: int, height: int, ratio: bool}>
     * }
     *
     * @throws RuntimeException when certificate_type is missing.
     */
    public function build(BondRequest $bondRequest): array
    {
        if ($bondRequest->certificate_type === null) {
            throw new RuntimeException(
                "Bond request #{$bondRequest->id} has no confirmation type. Cannot generate confirmation."
            );
        }

        $shared = $this->buildShared($bondRequest);

        if ($bondRequest->certificate_type === CertificateType::CarCertificate) {
            $specific = $this->buildCarSpecific($bondRequest);
        } else {
            $specific = $this->buildBondSpecific($bondRequest);
        }

        return [
            'text' => array_merge($shared['text'], $specific['text']),
            'images' => array_merge($shared['images'], $specific['images']),
        ];
    }

    /**
     * Merge verification placeholders added during certificate generation.
     *
     * @param  array{
     *   text: array<string, string>,
     *   images: array<string, array{path: string, width: int, height: int, ratio: bool}>
     * }  $data
     * @param  array{path: string, width: int, height: int, ratio: bool}  $qrImage
     * @return array{
     *   text: array<string, string>,
     *   images: array<string, array{path: string, width: int, height: int, ratio: bool}>
     * }
     */
    public function mergeVerificationPlaceholders(
        array $data,
        string $confirmationNumber,
        array $qrImage,
    ): array {
        $data['text']['Confirmation Number'] = $confirmationNumber;
        $data['images']['QR'] = $qrImage;

        return $data;
    }

    // -------------------------------------------------------------------------
    // Shared placeholders (present in both Bond and CAR templates)
    // -------------------------------------------------------------------------

    /**
     * @return array{text: array<string, string>, images: array<string, mixed>}
     */
    private function buildShared(BondRequest $bondRequest): array
    {
        $amount = $this->resolveAmountInWords($bondRequest);
        $signatory = $bondRequest->signatory;

        if ($signatory === null) {
            Log::warning("Bond request #{$bondRequest->id}: signatory is missing. Signatory and Position will be blank.");
        }

        $isCarEndorsementRequest = $this->isCarEndorsementRequest($bondRequest);

        return [
            'text' => [
                'Date' => DateFormatter::longDate($bondRequest->request_date),
                'Date issued' => $isCarEndorsementRequest ? '' : DateFormatter::longDate($bondRequest->date_issued),
                'Expiry date' => (string) ($bondRequest->expiry_date ?? ''),
                'Obligee' => (string) ($bondRequest->obligee_name ?? ''),
                'Address line 1' => $this->resolveAddressLine1($bondRequest),
                'Address Sentence' => $this->resolveAddressSentence($bondRequest),
                'Address line 2' => (string) ($bondRequest->address_2 ?? ''),
                'Address line 3' => (string) ($bondRequest->address_3 ?? ''),
                'Project name' => (string) ($bondRequest->project_name ?? ''),
                'Amount' => $this->formatAmount($bondRequest->amount),
                'Amount in words' => $amount,
                'Tin' => $this->resolveTin($bondRequest, $signatory),
                'Branch city' => $this->resolveBranchCity($bondRequest),
                'Signatory' => (string) ($signatory?->name ?? ''),
                'Position' => (string) (filled($signatory?->position)
                    ? $signatory->position
                    : ($bondRequest->signatory_position ?? '')),
                'Doc. No.' => $this->resolveLabeledDocField($bondRequest, 'Doc. No.', $bondRequest->doc_no),
                'Page No.' => $this->resolveLabeledDocField($bondRequest, 'Page No.', $bondRequest->page_no),
                'Book No.' => $this->resolveLabeledDocField($bondRequest, 'Book No.', $bondRequest->book_no),
                'Endorsement No.' => $this->resolveEndorsementNumber($bondRequest),
                'Date in words' => $this->resolveDateInWords($bondRequest),
                'Date issued in words' => $isCarEndorsementRequest ? '' : DateFormatter::inWords($bondRequest->date_issued),
                'Extension start' => $this->resolveExtensionStart($bondRequest),
                'Validity Ext' => (string) ($bondRequest->validity_extension ?? ''),
                'Jurat bold' => $this->resolveJuratTemplate($bondRequest, 'government_bold'),
                'Jurat before date' => $this->resolveJuratTemplate($bondRequest, 'government_rest_before_date'),
                'Jurat before city' => $this->resolveJuratTemplate($bondRequest, 'government_rest_before_city'),
                'City of Makati' => $this->resolveConfirmationCity($bondRequest),
                'Jurat before tin' => $this->resolveJuratTemplate($bondRequest, 'government_rest_before_tin'),
                'Jurat after tin' => $this->resolveJuratTemplate($bondRequest, 'government_rest_after_tin'),
                'Endorsement' => $this->resolveEndorsementTemplate($bondRequest),
            ],
            'images' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Bond Certificate specific placeholders
    // -------------------------------------------------------------------------

    /**
     * @return array{text: array<string, string>, images: array<string, mixed>}
     */
    private function buildBondSpecific(BondRequest $bondRequest): array
    {
        $principal = $bondRequest->principal;
        $notary = $bondRequest->notary;
        $signatory = $bondRequest->signatory;

        if ($principal === null) {
            Log::warning("Bond request #{$bondRequest->id}: principal is missing. PRINCIPAL and Bond will be blank.");
        }

        if ($notary === null) {
            Log::warning("Bond request #{$bondRequest->id}: notary is missing. Notary will be blank.");
        }

        $bondLabel = strtoupper($bondRequest->bond_label ?? '');
        $principalName = (string) ($principal?->company_name ?? $bondRequest->principal_name ?? '');

        $text = [
            'Bond' => $bondLabel,
            'BOND' => $bondLabel,
            'PRINCIPAL' => strtoupper($principalName),
            'Series year' => $this->resolveSeriesYearField($bondRequest, $bondRequest->series_year),
        ];

        $images = [];

        $signatureImage = $bondRequest->include_signatory_signature
            ? $this->resolveSignatureImage($bondRequest, $signatory)
            : null;

        if ($signatureImage !== null) {
            $images['Signature'] = $signatureImage;
        } else {
            $text['Signature'] = '';
        }

        $notarySealImage = $bondRequest->require_notary
            ? $this->resolveNotarySealImage($bondRequest, $notary)
            : null;

        if ($notarySealImage !== null) {
            $images['Notary'] = $notarySealImage;
        } else {
            $text['Notary'] = '';
        }

        return ['text' => $text, 'images' => $images];
    }

    // -------------------------------------------------------------------------
    // CAR Certificate specific placeholders
    // -------------------------------------------------------------------------

    /**
     * @return array{text: array<string, string>, images: array<string, mixed>}
     */
    private function buildCarSpecific(BondRequest $bondRequest): array
    {
        $principal = $bondRequest->principal;
        $notary = $bondRequest->notary;
        $images = [];
        $text = [];

        if ($principal === null) {
            Log::warning("Bond request #{$bondRequest->id}: principal is missing. Principal will be blank.");
        }

        if ($bondRequest->require_notary && $notary === null) {
            Log::warning("Bond request #{$bondRequest->id}: notary is missing. Notary will be blank.");
        }

        if ($bondRequest->include_signatory_signature) {
            $signatureImage = $this->resolveSignatureImage($bondRequest, $bondRequest->signatory);

            if ($signatureImage !== null) {
                $images['Signature'] = $signatureImage;
            } else {
                $text['Signature'] = '';
            }
        } else {
            $text['Signature'] = '';
        }

        $notarySealImage = $bondRequest->require_notary
            ? $this->resolveNotarySealImage($bondRequest, $notary)
            : null;

        if ($notarySealImage !== null) {
            $images['Notary'] = $notarySealImage;
        } else {
            $text['Notary'] = '';
        }

        return [
            'text' => array_merge([
                'CAR' => (string) ($bondRequest->car ?? ''),
                'Branch' => $this->resolveBranchName($bondRequest),
                'Year' => $this->resolveSeriesYearField($bondRequest, $bondRequest->series_year),
                'Attention' => (string) ($bondRequest->attention ?? ''),
                'Authorized Representative' => (string) ($bondRequest->authorized_representative ?? ''),
                'Principal' => (string) ($principal?->company_name ?? $bondRequest->principal_name ?? ''),
            ], $text),
            'images' => $images,
        ];
    }

    // -------------------------------------------------------------------------
    // Value resolvers
    // -------------------------------------------------------------------------

    private function resolveTin(BondRequest $bondRequest, mixed $signatory): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        return (string) ($signatory?->tin ?? $bondRequest->tin ?? '');
    }

    private function resolveDateInWords(BondRequest $bondRequest): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        return DateFormatter::inWords($bondRequest->request_date);
    }

    private function resolveAmountInWords(BondRequest $bondRequest): string
    {
        $stored = (string) ($bondRequest->amount_in_words ?? '');

        if ($stored !== '') {
            return strtoupper($stored);
        }

        if ($bondRequest->amount !== null && $bondRequest->amount !== '') {
            return strtoupper($this->amountToWords->convert($bondRequest->amount));
        }

        Log::warning("Bond request #{$bondRequest->id}: amount_in_words is empty and amount is null. Amount in words will be blank.");

        return '';
    }

    private function resolveBranchCity(BondRequest $bondRequest): string
    {
        $branchCity = $bondRequest->creator?->branch?->branch_city;

        if ($branchCity !== null && $branchCity !== '') {
            return $branchCity;
        }

        $branchCityFallback = $bondRequest->creator?->branch?->address
            ?? $bondRequest->branch_city
            ?? '';

        return (string) $branchCityFallback;
    }

    private function resolveBranchName(BondRequest $bondRequest): string
    {
        return (string) ($bondRequest->creator?->branch?->name
            ?? $bondRequest->branch
            ?? '');
    }

    private function resolveAddressLine1(BondRequest $bondRequest): string
    {
        return (string) ($bondRequest->address_1 ?? '');
    }

    private function resolveAddressSentence(BondRequest $bondRequest): string
    {
        $addressLines = $this->splitAddressLines($bondRequest->address_1);
        $ctmLines = $this->splitAddressLines($bondRequest->address_2);
        $provinceLines = $this->splitAddressLines($bondRequest->address_3);

        $maxRows = max(count($addressLines), count($ctmLines), count($provinceLines));

        if ($maxRows === 0) {
            return '';
        }

        $combinedRows = [];

        for ($index = 0; $index < $maxRows; $index++) {
            $segments = array_values(array_filter([
                trim((string) ($addressLines[$index] ?? '')),
                trim((string) ($ctmLines[$index] ?? '')),
                trim((string) ($provinceLines[$index] ?? '')),
            ], static fn (string $value): bool => $value !== ''));

            if ($segments !== []) {
                $combinedRows[] = implode(', ', $segments);
            }
        }

        return implode("\n", $combinedRows);
    }

    /**
     * @return array<int, string>
     */
    private function splitAddressLines(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $value);

        if (! is_array($lines)) {
            return [];
        }

        return array_map(static fn (string $line): string => trim($line), $lines);
    }

    private function resolveJuratTemplate(BondRequest $bondRequest, string $configKey): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        return (string) config("certificates.jurat_templates.{$configKey}", '');
    }

    private function resolveConfirmationCity(BondRequest $bondRequest): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        return (string) config('certificates.confirmation_city', 'City of Makati');
    }

    private function resolveLabeledDocField(BondRequest $bondRequest, string $label, mixed $value): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        $formattedValue = trim((string) ($value ?? ''));

        return $formattedValue === '' ? $label : "{$label} {$formattedValue}";
    }

    private function resolveSeriesYearField(BondRequest $bondRequest, mixed $value): string
    {
        if (! $bondRequest->require_notary) {
            return '';
        }

        $formattedValue = trim((string) ($value ?? ''));

        return $formattedValue === '' ? 'Series of ' : "Series of {$formattedValue}";
    }

    private function resolveEndorsementTemplate(BondRequest $bondRequest): string
    {
        if (! $bondRequest->include_endorsement_number) {
            return '';
        }

        return (string) config('certificates.endorsement_template', '');
    }

    private function resolveEndorsementNumber(BondRequest $bondRequest): string
    {
        if (! $bondRequest->include_endorsement_number) {
            return '';
        }

        return (string) ($bondRequest->endorsement_number ?? '');
    }

    private function resolveExtensionStart(BondRequest $bondRequest): string
    {
        if (! $this->isCarEndorsementRequest($bondRequest)) {
            return '';
        }

        return DateFormatter::longDate($bondRequest->extension_period_start);
    }

    private function isCarEndorsementRequest(BondRequest $bondRequest): bool
    {
        return $bondRequest->certificate_type === CertificateType::CarCertificate
            && (bool) $bondRequest->include_endorsement_number;
    }

    /**
     * Resolve the signature image data for TemplateProcessor::setImageValue().
     * Returns null when no usable image exists (caller should fall back to empty text).
     *
     * @return array{path: string, width: int, height: int, ratio: bool}|null
     */
    private function resolveSignatureImage(BondRequest $bondRequest, mixed $signatory): ?array
    {
        $signaturePath = $signatory?->signature_path ?? null;

        if ($signaturePath === null || $signaturePath === '') {
            Log::warning("Bond request #{$bondRequest->id}: signatory has no signature image. Signature placeholder will be blank.");

            return null;
        }

        $absolutePath = Storage::disk('public')->path($signaturePath);

        if (! file_exists($absolutePath)) {
            Log::warning("Bond request #{$bondRequest->id}: signature file does not exist at {$absolutePath}. Signature placeholder will be blank.");

            return null;
        }

        return [
            'path' => $absolutePath,
            'width' => 120,
            'height' => 60,
            'ratio' => true,
        ];
    }

    /**
     * Resolve the notary seal image for TemplateProcessor::setImageValue().
     * Returns null when no usable image exists (caller falls back to empty text).
     *
     * @return array{path: string, width: int, height: int, ratio: bool}|null
     */
    private function resolveNotarySealImage(BondRequest $bondRequest, mixed $notary): ?array
    {
        $sealPath = $notary?->signature_path ?? null;

        if ($sealPath === null || $sealPath === '') {
            Log::warning("Bond request #{$bondRequest->id}: notary has no seal image. Notary placeholder will be blank.");

            return null;
        }

        $absolutePath = Storage::disk('public')->path($sealPath);

        if (! file_exists($absolutePath)) {
            Log::warning("Bond request #{$bondRequest->id}: notary seal file does not exist at {$absolutePath}. Notary placeholder will be blank.");

            return null;
        }

        return [
            'path' => $absolutePath,
            'width' => 250,
            'height' => 250,
            'ratio' => true,
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return 'PHP '.number_format((float) $amount, 2);
    }
}
