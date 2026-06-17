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
     * Resolve a certificate version from an exact confirmation number match.
     */
    public function findVersionByLookup(string $input): ?CertificateVersion
    {
        $normalized = strtoupper(trim($input));

        if ($normalized === '') {
            return null;
        }

        return CertificateVersion::query()
            ->where('confirmation_number', $normalized)
            ->first();
    }
}
