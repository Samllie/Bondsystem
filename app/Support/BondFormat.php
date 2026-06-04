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
}
