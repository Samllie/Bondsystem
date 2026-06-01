<?php

namespace App\Enums;

enum BondRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Notarized = 'notarized';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Notarized => 'Notarized',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Notarized => 'green',
            self::Expired => 'slate',
            self::Cancelled => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ])->all();
    }
}
