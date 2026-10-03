<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MoneyInput implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value)) ||
            ! preg_match('/^(0|[1-9][0-9]{0,12})(\\.[0-9]{1,2})?$/D', (string) $value)) {
            $fail('The :attribute must be a non-negative decimal string (up to 13 integer and 2 decimal digits) or an integer.');
        }
    }
}
