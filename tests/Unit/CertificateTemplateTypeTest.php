<?php

namespace Tests\Unit;

use App\Enums\CertificateTemplateType;
use App\Enums\CertificateType;
use App\Models\BondRequest;
use Tests\TestCase;

class CertificateTemplateTypeTest extends TestCase
{
    public function test_car_confirmation_uses_endorsement_template_when_bond_request_requires_endorsement(): void
    {
        $bondRequest = BondRequest::factory()->make([
            'certificate_type' => CertificateType::CarCertificate,
            'include_endorsement_number' => true,
        ]);

        request()->setRouteResolver(fn () => new class($bondRequest)
        {
            public function __construct(private BondRequest $bondRequest) {}

            public function parameter(string $name): mixed
            {
                return $name === 'bond_request' ? $this->bondRequest : null;
            }
        });

        $this->assertSame(
            CertificateTemplateType::CarCertificateEndorsement,
            CertificateTemplateType::fromCertificateType(CertificateType::CarCertificate),
        );
    }

    public function test_car_confirmation_uses_standard_template_without_endorsement(): void
    {
        $bondRequest = BondRequest::factory()->make([
            'certificate_type' => CertificateType::CarCertificate,
            'include_endorsement_number' => false,
        ]);

        request()->setRouteResolver(fn () => new class($bondRequest)
        {
            public function __construct(private BondRequest $bondRequest) {}

            public function parameter(string $name): mixed
            {
                return $name === 'bond_request' ? $this->bondRequest : null;
            }
        });

        $this->assertSame(
            CertificateTemplateType::Car,
            CertificateTemplateType::fromCertificateType(CertificateType::CarCertificate),
        );
    }
}
