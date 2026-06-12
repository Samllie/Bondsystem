<?php

namespace App\Enums;

enum PartyType: string
{
    case Government = 'government';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Private => 'Private',
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
