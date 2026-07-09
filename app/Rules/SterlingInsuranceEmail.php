<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SterlingInsuranceEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_ends_with(strtolower($value), '@sterling-insurance.com.ph')) {
            $fail('The email must use the @sterling-insurance.com.ph domain.');
        }
    }
}
