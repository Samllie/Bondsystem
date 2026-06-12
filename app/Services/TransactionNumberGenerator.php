<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionNumberGenerator
{
    private const LOCK_KEY = 'transaction-number-generator';

    private const LOCK_SECONDS = 10;

    /**
     * Generate a system-wide unique transaction number (TXN-YYYYMMDD-#####).
     */
    public static function generate(): string
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        try {
            return $lock->block(5, fn (): string => self::nextNumber());
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('Unable to acquire transaction number lock.', 0, $exception);
        }
    }

    private static function nextNumber(): string
    {
        return DB::transaction(function (): string {
            Transaction::query()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $date = now()->format('Ymd');
            $prefix = "TXN-{$date}-";

            $latest = Transaction::query()
                ->where('transaction_number', 'like', $prefix.'%')
                ->orderByDesc('transaction_number')
                ->value('transaction_number');

            $sequence = $latest
                ? ((int) substr($latest, strlen($prefix))) + 1
                : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
