<?php

namespace App\Enums;

use App\Models\BondRequest;

enum CertificateTemplateType: string
{
    case Bond = 'bond';
    case Car = 'car';
    case CarCertificateEndorsement = 'car_certificate_endorsement';

    public function label(): string
    {
        return match ($this) {
            self::Bond => 'Bond',
            self::Car => 'CAR',
            self::CarCertificateEndorsement => 'CAR Confirmation with Endorsement',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->all();
    }

    public static function fromCertificateType(CertificateType $certificateType, bool $includeEndorsementNumber = false): self
    {
        $requestBondRequest = request()->route('bond_request');
        $shouldUseEndorsementTemplate = $includeEndorsementNumber
            || ($requestBondRequest instanceof BondRequest && $requestBondRequest->include_endorsement_number);

        if ($certificateType === CertificateType::CarCertificate && $shouldUseEndorsementTemplate) {
            return self::CarCertificateEndorsement;
        }

        return $certificateType === CertificateType::CarCertificate
            ? self::Car
            : self::Bond;
    }

    public static function fromBondRequest(BondRequest $bondRequest): self
    {
        return self::fromCertificateType(
            $bondRequest->certificate_type,
            (bool) $bondRequest->include_endorsement_number,
        );
    }
}
