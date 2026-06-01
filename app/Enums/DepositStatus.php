<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ])->all();
    }
}
