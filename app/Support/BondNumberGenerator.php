<?php

namespace App\Support;

use App\Models\Maintenance\BondTypeMaster;
use App\Models\User;

class BondNumberGenerator
{
    public static function fromBondType(BondTypeMaster $bondType): string
    {
        return (string) $bondType->code;
    }

    public static function branchCodeFor(User $user): ?string
    {
        if (filled($user->branch_code)) {
            return strtoupper($user->branch_code);
        }

        $code = $user->branch?->branch_code;

        return $code ? strtoupper($code) : null;
    }

    public static function userHasBranchCode(User $user): bool
    {
        return filled(self::branchCodeFor($user));
    }
}
