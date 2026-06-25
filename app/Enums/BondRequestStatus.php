<?php

namespace App\Enums;

enum BondRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case PendingForChanges = 'pending_for_changes';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Notarized = 'notarized';
    case Returned = 'returned';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::PendingForChanges => 'Pending for Changes',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Notarized => 'Notarized',
            self::Returned => 'Returned',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'amber',
            self::PendingForChanges => 'orange',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Notarized => 'green',
            self::Returned => 'teal',
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
