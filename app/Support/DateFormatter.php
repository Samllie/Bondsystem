<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formats dates for certificate templates.
 */
class DateFormatter
{
    /** @var array<int, string> Day ordinals 1–31. */
    private const DAY_ORDINALS = [
        1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD', 4 => 'FOURTH', 5 => 'FIFTH',
        6 => 'SIXTH', 7 => 'SEVENTH', 8 => 'EIGHTH', 9 => 'NINTH', 10 => 'TENTH',
        11 => 'ELEVENTH', 12 => 'TWELFTH', 13 => 'THIRTEENTH', 14 => 'FOURTEENTH',
        15 => 'FIFTEENTH', 16 => 'SIXTEENTH', 17 => 'SEVENTEENTH', 18 => 'EIGHTEENTH',
        19 => 'NINETEENTH', 20 => 'TWENTIETH', 21 => 'TWENTY-FIRST', 22 => 'TWENTY-SECOND',
        23 => 'TWENTY-THIRD', 24 => 'TWENTY-FOURTH', 25 => 'TWENTY-FIFTH', 26 => 'TWENTY-SIXTH',
        27 => 'TWENTY-SEVENTH', 28 => 'TWENTY-EIGHTH', 29 => 'TWENTY-NINTH', 30 => 'THIRTIETH',
        31 => 'THIRTY-FIRST',
    ];

    private const ONES = [
        '', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE',
        'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN',
        'SEVENTEEN', 'EIGHTEEN', 'NINETEEN',
    ];

    private const TENS = [
        '', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY',
    ];

    /**
     * Format a date as "SEVENTH DAY OF JUNE TWO THOUSAND TWENTY-SIX".
     * Used for formal certificate attestation lines.
     */
    public static function writtenDate(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        $day = (int) $carbon->format('j');
        $dayWord = self::DAY_ORDINALS[$day] ?? '';
        $month = strtoupper($carbon->format('F'));
        $yearWord = self::yearInWords((int) $carbon->format('Y'));

        return "{$dayWord} DAY OF {$month} {$yearWord}";
    }

    /**
     * Format a date as "June 07, 2026" (zero-padded day, long month name).
     * Used for the [[Date]] and [[Date issued]] certificate placeholders.
     */
    public static function longDate(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return $carbon->format('F d, Y');
    }

    /**
     * Format a date as "1st day of January, 2025".
     * Kept for backward compatibility with frontend-mirroring logic.
     */
    public static function inWords(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        $day = (int) $carbon->format('j');
        $suffix = self::daySuffix($day);
        $month = $carbon->format('F');
        $year = $carbon->format('Y');

        return "{$day}{$suffix} day of {$month}, {$year}";
    }

    /**
     * Format a date as "01/15/2025".
     */
    public static function shortDate(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return $carbon->format('m/d/Y');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert a year integer to uppercase English words.
     * Supports years 1000–9999.
     *
     * Examples:
     *   2026 → "TWO THOUSAND TWENTY-SIX"
     *   2000 → "TWO THOUSAND"
     *   2015 → "TWO THOUSAND FIFTEEN"
     *   1999 → "ONE THOUSAND NINE HUNDRED NINETY-NINE"
     */
    private static function yearInWords(int $year): string
    {
        $parts = [];

        $thousands = intdiv($year, 1000);
        if ($thousands > 0) {
            $parts[] = self::ONES[$thousands].' THOUSAND';
        }

        $remainder = $year % 1000;
        $hundreds = intdiv($remainder, 100);
        if ($hundreds > 0) {
            $parts[] = self::ONES[$hundreds].' HUNDRED';
        }

        $twoDigit = $remainder % 100;
        if ($twoDigit > 0) {
            if ($twoDigit < 20) {
                $parts[] = self::ONES[$twoDigit];
            } else {
                $tensWord = self::TENS[intdiv($twoDigit, 10)];
                $onesDigit = $twoDigit % 10;
                $parts[] = $onesDigit > 0
                    ? $tensWord.'-'.self::ONES[$onesDigit]
                    : $tensWord;
            }
        }

        return implode(' ', $parts);
    }

    private static function daySuffix(int $day): string
    {
        $remainderTen = $day % 10;
        $remainderHundred = $day % 100;

        if ($remainderTen === 1 && $remainderHundred !== 11) {
            return 'st';
        }

        if ($remainderTen === 2 && $remainderHundred !== 12) {
            return 'nd';
        }

        if ($remainderTen === 3 && $remainderHundred !== 13) {
            return 'rd';
        }

        return 'th';
    }
}
