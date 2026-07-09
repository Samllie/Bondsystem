<?php

namespace App\Services;

use App\Support\AmountInWords;

/**
 * Service wrapper around AmountInWords for dependency injection.
 *
 * Output example: "ONE MILLION FIVE HUNDRED THOUSAND & 00/100 ONLY"
 */
class AmountToWordsService
{
    /**
     * Convert a numeric amount to its Filipino peso word form.
     * Returns an empty string when the amount is null or empty.
     */
    public function convert(float|string|int|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return AmountInWords::format($amount);
    }
}
