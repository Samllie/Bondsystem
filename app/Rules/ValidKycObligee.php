<?php

namespace App\Rules;

use App\Services\KycObligeeService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidKycObligee implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('The selected obligee is invalid.');

            return;
        }

        $obligee = app(KycObligeeService::class)->find((int) $value);

        if ($obligee === null) {
            $fail('The selected obligee is invalid.');
        }
    }
}
