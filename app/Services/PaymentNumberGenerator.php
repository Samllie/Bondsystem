<?php

namespace App\Services;

use App\Models\PaymentHistory;

class PaymentNumberGenerator
{
    public static function generate(): string
    {
        $date = now()->format('Ymd');
        $prefix = "PAY-{$date}-";

        $latest = PaymentHistory::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('payment_number')
            ->value('payment_number');

        $sequence = $latest
            ? ((int) substr($latest, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
