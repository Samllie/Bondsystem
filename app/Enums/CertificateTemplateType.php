<?php

namespace App\Enums;

enum CertificateTemplateType: string
{
    case Bond = 'bond';
    case Car = 'car';

    public function label(): string
    {
        return match ($this) {
            self::Bond => 'Bond',
            self::Car => 'CAR',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->all();
    }

    public static function fromCertificateType(CertificateType $certificateType): self
    {
        return $certificateType === CertificateType::CarCertificate
            ? self::Car
            : self::Bond;
    }
}
