<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_formats_whole_pesos(): void
    {
        $this->assertSame('ONE THOUSAND & 00/100 ONLY', AmountInWords::format(1000));
    }

    public function test_formats_pesos_with_centavos(): void
    {
        $this->assertSame('ONE HUNDRED & 50/100 ONLY', AmountInWords::format(100.5));
    }

    public function test_formats_zero(): void
    {
        $this->assertSame('ZERO & 00/100 ONLY', AmountInWords::format(0));
    }

    public function test_formats_billions(): void
    {
        $this->assertSame(
            'TWO BILLION FIVE HUNDRED MILLION & 00/100 ONLY',
            AmountInWords::format(2_500_000_000)
        );
    }

    public function test_formats_trillions_with_centavos(): void
    {
        $this->assertSame(
            'ONE TRILLION TWO HUNDRED THIRTY FOUR BILLION FIVE HUNDRED SIXTY SEVEN MILLION EIGHT HUNDRED NINETY THOUSAND ONE HUNDRED TWENTY THREE & 45/100 ONLY',
            AmountInWords::format('1234567890123.45')
        );
    }

    public function test_formats_quadrillions(): void
    {
        $this->assertSame(
            'THREE QUADRILLION TWO HUNDRED TRILLION & 00/100 ONLY',
            AmountInWords::format('3200000000000000')
        );
    }
}
