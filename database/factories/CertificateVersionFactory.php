<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificateVersion>
 */
class CertificateVersionFactory extends Factory
{
    protected $model = CertificateVersion::class;

    public function definition(): array
    {
        $versionNumber = fake()->numberBetween(1, 9);
        $random = Str::upper(bin2hex(random_bytes(4)));

        return [
            'bond_request_id' => BondRequest::factory(),
            'version_number' => $versionNumber,
            'certificate_type' => CertificateType::BondCertificate,
            'template_id' => null,
            'docx_path' => 'private/generated-docx/2026/06/request_1_v1.docx',
            'pdf_path' => 'private/certificates/2026/06/request_1_v1.pdf',
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'is_current' => true,
            'remarks' => null,
            'confirmation_number' => "SICI-BOND-2026-{$random}-V{$versionNumber}",
            'verification_token' => bin2hex(random_bytes(32)),
            'qr_code_path' => 'private/qr-codes/2026/06/certificate_version_1.png',
            'verification_count' => 0,
            'last_verified_at' => null,
        ];
    }

    public function current(): static
    {
        return $this->state(['is_current' => true]);
    }

    public function notCurrent(): static
    {
        return $this->state(['is_current' => false]);
    }
}
