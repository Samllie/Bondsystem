<?php

namespace Tests\Unit;

use App\Support\BondFormat;
use PHPUnit\Framework\TestCase;

class BondFormatTest extends TestCase
{
    public function test_build_value_uses_bond_number_and_branch_code_format(): void
    {
        $value = BondFormat::buildValue(
            'Retention Money Bond',
            'mkt',
            'G(42)',
        );

        $this->assertSame('RETENTION MONEY BOND NO. G(42)-MKT-', $value);
    }

    public function test_build_car_value_uses_branch_code_and_serial_format(): void
    {
        $value = BondFormat::buildCarValue('mkt');

        $this->assertSame('CAR-MKT-0072056', $value);
    }
}
