<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateVersion>
 */
class CertificateVersionFactory extends Factory
{
    protected $model = CertificateVersion::class;

    public function definition(): array
    {
        $bondRequest = BondRequest::factory();

        return [
            'bond_request_id' => $bondRequest,
            'version_number' => 1,
            'certificate_type' => CertificateType::BondCertificate,
            'template_id' => null,
            'docx_path' => 'private/generated-docx/2026/06/request_1_v1.docx',
            'pdf_path' => 'private/certificates/2026/06/request_1_v1.pdf',
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'is_current' => true,
            'remarks' => null,
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
