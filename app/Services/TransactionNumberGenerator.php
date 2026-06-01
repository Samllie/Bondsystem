<?php

namespace App\Services;

use App\Models\Transaction;

class TransactionNumberGenerator
{
    public static function generate(): string
    {
        $date = now()->format('Ymd');
        $prefix = "TXN-{$date}-";

        $latest = Transaction::query()
            ->where('transaction_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $latest
            ? ((int) substr($latest, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
