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
            $fail('Please select an obligee from the list.');

            return;
        }

        $id = (int) $value;

        if ($id < 1) {
            $fail('Please select an obligee from the list.');

            return;
        }

        $obligee = app(KycObligeeService::class)->find($id);

        if ($obligee === null) {
            $fail('The selected obligee is invalid.');
        }
    }
}
