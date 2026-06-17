<?php

namespace App\Services;

use App\Enums\CertificateType;
use App\Models\CertificateVersion;
use Illuminate\Support\Str;

class ConfirmationNumberService
{
    public function generate(CertificateType $certificateType, int $versionNumber, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $typeCode = $certificateType === CertificateType::CarCertificate ? 'CAR' : 'BOND';

        do {
            $random = Str::upper(bin2hex(random_bytes(4)));
            $confirmationNumber = "SICI-{$typeCode}-{$year}-{$random}-V{$versionNumber}";
        } while (CertificateVersion::query()->where('confirmation_number', $confirmationNumber)->exists());

        return $confirmationNumber;
    }

    public function generateVerificationToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (CertificateVersion::query()->where('verification_token', $token)->exists());

        return $token;
    }

    /**
     * Resolve a certificate version from a full confirmation number or its 8-character hex segment.
     */
    public function findVersionByLookup(string $input): ?CertificateVersion
    {
        $normalized = $this->normalizeConfirmationInput($input);

        if ($normalized === '') {
            return null;
        }

        $exactMatch = CertificateVersion::query()
            ->where('confirmation_number', $normalized)
            ->first();

        if ($exactMatch !== null) {
            return $exactMatch;
        }

        if (preg_match('/^[A-F0-9]{8}$/', $normalized)) {
            return CertificateVersion::query()
                ->where('confirmation_number', 'like', "%-{$normalized}-%")
                ->first();
        }

        return null;
    }

    private function normalizeConfirmationInput(string $input): string
    {
        $input = strtoupper(trim($input));
        $input = str_replace(
            ["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}", '−', '–', '—'],
            '-',
            $input,
        );
        $input = preg_replace('/\s*-\s*/', '-', $input) ?? $input;

        if (preg_match(
            '/^SICI\s+(BOND|CAR)\s+(\d{4})\s+([A-F0-9]{8})\s+V(\d+)$/',
            preg_replace('/\s+/', ' ', $input) ?? $input,
            $matches,
        )) {
            return sprintf('SICI-%s-%s-%s-V%s', $matches[1], $matches[2], $matches[3], $matches[4]);
        }

        $input = preg_replace('/\s+/', '', $input) ?? $input;

        return $input;
    }
}
