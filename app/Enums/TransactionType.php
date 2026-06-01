<?php

namespace App\Enums;

enum TransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::Debit => 'Debit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Credit => 'green',
            self::Debit => 'red',
        };
    }
}
