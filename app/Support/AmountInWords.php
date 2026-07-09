<?php

namespace App\Support;

class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /** @var array<string, string> */
    private const SCALES = [
        '1000000000000000' => 'Quadrillion',
        '1000000000000' => 'Trillion',
        '1000000000' => 'Billion',
        '1000000' => 'Million',
        '1000' => 'Thousand',
    ];

    public static function format(float|string|int $amount): string
    {
        [$pesos, $centavos, $negative] = self::parseAmount($amount);

        if ($negative) {
            $unsigned = $centavos > 0
                ? $pesos.'.'.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT)
                : $pesos;

            return 'Negative '.self::format($unsigned);
        }

        $words = self::convertNumber($pesos);

        $centavosPart = str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);

        return strtoupper($words.' & '.$centavosPart.'/100 Only');
    }

    /**
     * @return array{0: string, 1: int, 2: bool}
     */
    private static function parseAmount(float|string|int $amount): array
    {
        $negative = false;

        if (is_int($amount)) {
            $raw = (string) $amount;
        } elseif (is_float($amount)) {
            $raw = sprintf('%.2f', $amount);
        } else {
            $raw = str_replace(',', '', trim($amount));
        }

        if (str_starts_with($raw, '-')) {
            $negative = true;
            $raw = substr($raw, 1);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
            $raw = sprintf('%.2f', (float) $raw);
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '0');
        $whole = ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');
        $centavos = (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return [$whole, $centavos, $negative];
    }

    private static function convertNumber(string $number): string
    {
        if (bccomp($number, '0') === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach (self::SCALES as $scale => $name) {
            if (bccomp($number, $scale) < 0) {
                continue;
            }

            $chunk = bcdiv($number, $scale, 0);
            $parts[] = self::convertChunk($chunk).' '.$name;
            $number = bcmod($number, $scale);
        }

        if (bccomp($number, '0') > 0) {
            $parts[] = self::convertChunk($number);
        }

        return implode(' ', $parts);
    }

    private static function convertChunk(string $chunk): string
    {
        return bccomp($chunk, '999') > 0
            ? self::convertNumber($chunk)
            : self::convertHundreds((int) $chunk);
    }

    private static function convertHundreds(int $number): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = self::ONES[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)];

            if ($number % 10 > 0) {
                $words[] = self::ONES[$number % 10];
            }
        } elseif ($number > 0) {
            $words[] = self::ONES[$number];
        }

        return trim(implode(' ', $words));
    }
}
