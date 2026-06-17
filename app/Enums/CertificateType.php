<?php

namespace App\Enums;

enum CertificateType: string
{
    case BondCertificate = 'bond_certificate';
    case CarCertificate = 'car_certificate';

    public function label(): string
    {
        return match ($this) {
            self::BondCertificate => 'Bond Confirmation',
            self::CarCertificate => 'CAR Confirmation',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->all();
    }
}
