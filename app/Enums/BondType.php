<?php

namespace App\Enums;

enum BondType: string
{
    case Performance = 'performance';
    case Bid = 'bid';
    case Payment = 'payment';
    case Warranty = 'warranty';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Performance => 'Performance Bond',
            self::Bid => 'Bid Bond',
            self::Payment => 'Payment Bond',
            self::Warranty => 'Warranty Bond',
            self::Other => 'Other',
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
