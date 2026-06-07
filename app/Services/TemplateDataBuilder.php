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
                "Bond request #{$bondRequest->id} has no certificate type. Cannot generate certificate."
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

        return [
            'text' => [
                'Date' => DateFormatter::longDate($bondRequest->request_date),
                'Date issued' => DateFormatter::longDate($bondRequest->date_issued),
                'Expiry date' => (string) ($bondRequest->expiry_date ?? ''),
                'Obligee' => (string) ($bondRequest->obligee_name ?? ''),
                'Address line 1' => (string) ($bondRequest->address_1 ?? ''),
                'Address line 2' => (string) ($bondRequest->address_2 ?? ''),
                'Address line 3' => (string) ($bondRequest->address_3 ?? ''),
                'Project name' => (string) ($bondRequest->project_name ?? ''),
                'Amount' => $this->formatAmount($bondRequest->amount),
                'Amount in words' => $amount,
                'Tin' => (string) ($bondRequest->tin ?? ''),
                'Branch city' => $this->resolveBranchCity($bondRequest),
                'Signatory' => (string) ($signatory?->name ?? ''),
                'Position' => (string) ($signatory?->position ?? ''),
                'Doc. No.' => (string) ($bondRequest->doc_no ?? ''),
                'Page No.' => (string) ($bondRequest->page_no ?? ''),
                'Book No.' => (string) ($bondRequest->book_no ?? ''),
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

        $bondLabel = $bondRequest->bond_label ?? '';
        $principalName = (string) ($principal?->company_name ?? $bondRequest->principal_name ?? '');

        $text = [
            'Bond' => $bondLabel,
            'BOND' => strtoupper($bondLabel),
            'PRINCIPAL' => strtoupper($principalName),
            'Date in words' => DateFormatter::writtenDate($bondRequest->request_date),
            'Date issued in words' => DateFormatter::writtenDate($bondRequest->date_issued),
            'Notary' => (string) ($notary?->name ?? ''),
            'Series year' => (string) ($bondRequest->series_year ?? ''),
        ];

        $images = [];
        $signatureImage = $this->resolveSignatureImage($bondRequest, $signatory);

        if ($signatureImage !== null) {
            $images['Signature'] = $signatureImage;
        } else {
            // Placeholder is in the template; replace with empty text so it doesn't
            // render as a raw ${Signature} token in the output document.
            $text['Signature'] = '';
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

        if ($principal === null) {
            Log::warning("Bond request #{$bondRequest->id}: principal is missing. Principal will be blank.");
        }

        return [
            'text' => [
                'CAR' => (string) ($bondRequest->car ?? ''),
                'Branch' => $this->resolveBranchName($bondRequest),
                'Year' => (string) ($bondRequest->series_year ?? ''),
                'Attention' => (string) ($bondRequest->attention ?? ''),
                'Authorized Representative' => (string) ($bondRequest->authorized_representative ?? ''),
                'Principal' => (string) ($principal?->company_name ?? $bondRequest->principal_name ?? ''),
            ],
            'images' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Value resolvers
    // -------------------------------------------------------------------------

    private function resolveAmountInWords(BondRequest $bondRequest): string
    {
        $stored = (string) ($bondRequest->amount_in_words ?? '');

        if ($stored !== '') {
            return $stored;
        }

        if ($bondRequest->amount !== null && $bondRequest->amount !== '') {
            return $this->amountToWords->convert($bondRequest->amount);
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

    private function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return 'PHP '.number_format((float) $amount, 2);
    }
}
