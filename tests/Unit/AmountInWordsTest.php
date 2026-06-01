<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_formats_whole_pesos(): void
    {
        $this->assertSame('One Thousand Pesos Only', AmountInWords::format(1000));
    }

    public function test_formats_pesos_with_centavos(): void
    {
        $this->assertSame('One Hundred Pesos and Fifty Centavos Only', AmountInWords::format(100.5));
    }

    public function test_formats_zero(): void
    {
        $this->assertSame('Zero Pesos Only', AmountInWords::format(0));
    }

    public function test_formats_billions(): void
    {
        $this->assertSame(
            'Two Billion Five Hundred Million Pesos Only',
            AmountInWords::format(2_500_000_000)
        );
    }

    public function test_formats_trillions_with_centavos(): void
    {
        $this->assertSame(
            'One Trillion Two Hundred Thirty Four Billion Five Hundred Sixty Seven Million Eight Hundred Ninety Thousand One Hundred Twenty Three Pesos and Forty Five Centavos Only',
            AmountInWords::format('1234567890123.45')
        );
    }

    public function test_formats_quadrillions(): void
    {
        $this->assertSame(
            'Three Quadrillion Two Hundred Trillion Pesos Only',
            AmountInWords::format('3200000000000000')
        );
    }
}
