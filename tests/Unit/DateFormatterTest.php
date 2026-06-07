<?php

namespace Tests\Unit;

use App\Support\DateFormatter;
use PHPUnit\Framework\TestCase;

class DateFormatterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // inWords (backward-compat ordinal format: "7th day of June, 2026")
    // -------------------------------------------------------------------------

    public function test_in_words_formats_first_day_of_month(): void
    {
        $this->assertSame('1st day of January, 2025', DateFormatter::inWords('2025-01-01'));
    }

    public function test_in_words_formats_second_day(): void
    {
        $this->assertSame('2nd day of February, 2025', DateFormatter::inWords('2025-02-02'));
    }

    public function test_in_words_formats_third_day(): void
    {
        $this->assertSame('3rd day of March, 2025', DateFormatter::inWords('2025-03-03'));
    }

    public function test_in_words_formats_fourth_day(): void
    {
        $this->assertSame('4th day of April, 2025', DateFormatter::inWords('2025-04-04'));
    }

    public function test_in_words_formats_eleventh_day(): void
    {
        $this->assertSame('11th day of November, 2025', DateFormatter::inWords('2025-11-11'));
    }

    public function test_in_words_formats_twenty_first_day(): void
    {
        $this->assertSame('21st day of December, 2025', DateFormatter::inWords('2025-12-21'));
    }

    public function test_in_words_returns_empty_string_for_null(): void
    {
        $this->assertSame('', DateFormatter::inWords(null));
    }

    public function test_in_words_returns_empty_string_for_empty_string(): void
    {
        $this->assertSame('', DateFormatter::inWords(''));
    }

    // -------------------------------------------------------------------------
    // writtenDate (certificate attestation: "SEVENTH DAY OF JUNE TWO THOUSAND TWENTY-SIX")
    // -------------------------------------------------------------------------

    public function test_written_date_formats_seventh_day_june_2026(): void
    {
        $this->assertSame(
            'SEVENTH DAY OF JUNE TWO THOUSAND TWENTY-SIX',
            DateFormatter::writtenDate('2026-06-07')
        );
    }

    public function test_written_date_formats_first_day_january_2000(): void
    {
        $this->assertSame(
            'FIRST DAY OF JANUARY TWO THOUSAND',
            DateFormatter::writtenDate('2000-01-01')
        );
    }

    public function test_written_date_formats_twenty_first_day_december_2025(): void
    {
        $this->assertSame(
            'TWENTY-FIRST DAY OF DECEMBER TWO THOUSAND TWENTY-FIVE',
            DateFormatter::writtenDate('2025-12-21')
        );
    }

    public function test_written_date_formats_thirtieth_day_april_2015(): void
    {
        $this->assertSame(
            'THIRTIETH DAY OF APRIL TWO THOUSAND FIFTEEN',
            DateFormatter::writtenDate('2015-04-30')
        );
    }

    public function test_written_date_formats_eleventh_day_november_1999(): void
    {
        $this->assertSame(
            'ELEVENTH DAY OF NOVEMBER ONE THOUSAND NINE HUNDRED NINETY-NINE',
            DateFormatter::writtenDate('1999-11-11')
        );
    }

    public function test_written_date_returns_empty_for_null(): void
    {
        $this->assertSame('', DateFormatter::writtenDate(null));
    }

    public function test_written_date_returns_empty_for_empty_string(): void
    {
        $this->assertSame('', DateFormatter::writtenDate(''));
    }

    // -------------------------------------------------------------------------
    // longDate (template placeholder format: "June 07, 2026")
    // -------------------------------------------------------------------------

    public function test_long_date_zero_pads_single_digit_day(): void
    {
        $this->assertSame('June 07, 2026', DateFormatter::longDate('2026-06-07'));
    }

    public function test_long_date_formats_double_digit_day(): void
    {
        $this->assertSame('January 15, 2025', DateFormatter::longDate('2025-01-15'));
    }

    public function test_long_date_returns_empty_for_null(): void
    {
        $this->assertSame('', DateFormatter::longDate(null));
    }

    // -------------------------------------------------------------------------
    // shortDate
    // -------------------------------------------------------------------------

    public function test_short_date_formats_correctly(): void
    {
        $this->assertSame('01/15/2025', DateFormatter::shortDate('2025-01-15'));
    }

    public function test_short_date_returns_empty_for_null(): void
    {
        $this->assertSame('', DateFormatter::shortDate(null));
    }
}
