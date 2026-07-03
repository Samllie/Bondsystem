<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_formats_whole_pesos(): void
    {
        $this->assertSame('ONE THOUSAND PESOS ONLY', AmountInWords::format(1000));
    }

    public function test_formats_pesos_with_centavos(): void
    {
        $this->assertSame('ONE HUNDRED PESOS AND FIFTY CENTAVOS ONLY', AmountInWords::format(100.5));
    }

    public function test_formats_zero(): void
    {
        $this->assertSame('ZERO PESOS ONLY', AmountInWords::format(0));
    }

    public function test_formats_billions(): void
    {
        $this->assertSame(
            'TWO BILLION FIVE HUNDRED MILLION PESOS ONLY',
            AmountInWords::format(2_500_000_000)
        );
    }

    public function test_formats_trillions_with_centavos(): void
    {
        $this->assertSame(
            'ONE TRILLION TWO HUNDRED THIRTY FOUR BILLION FIVE HUNDRED SIXTY SEVEN MILLION EIGHT HUNDRED NINETY THOUSAND ONE HUNDRED TWENTY THREE PESOS AND FORTY FIVE CENTAVOS ONLY',
            AmountInWords::format('1234567890123.45')
        );
    }

    public function test_formats_quadrillions(): void
    {
        $this->assertSame(
            'THREE QUADRILLION TWO HUNDRED TRILLION PESOS ONLY',
            AmountInWords::format('3200000000000000')
        );
    }
}
