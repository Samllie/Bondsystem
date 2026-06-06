<?php

namespace App\Support;

class BondFormat
{
    public static function buildValue(
        string $bondTypeLabel,
        ?string $branchCode,
        ?string $bondNumber,
        ?string $serial,
    ): string {
        if ($bondTypeLabel === '' || $bondNumber === null || $bondNumber === '' || $branchCode === null || $branchCode === '' || $serial === null || $serial === '') {
            return '';
        }

        return sprintf(
            '%s NO. %s-%s-%s',
            $bondTypeLabel,
            $bondNumber,
            strtoupper($branchCode),
            $serial,
        );
    }

    public static function buildCarValue(?string $branchCode, string $serial = '0072056'): string
    {
        $code = strtoupper(trim((string) $branchCode));
        $digits = str_pad(preg_replace('/\D/', '', $serial) ?: '0', 7, '0', STR_PAD_LEFT);
        $digits = substr($digits, -7);

        if ($code === '') {
            return '';
        }

        return sprintf('CAR-%s-%s', $code, $digits);
    }
}
