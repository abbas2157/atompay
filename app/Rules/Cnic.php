<?php

namespace App\Rules;

use App\Support\Pakistan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A NADRA-issued CNIC: 13 digits, written 42101-1234567-1. The value reaching
 * this rule has already been reduced to digits by the form request, so the
 * messages talk about digits, not dashes.
 */
class Cnic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = Pakistan::normalizeCnic((string) $value);

        if ($digits === '' || ! ctype_digit($digits)) {
            $fail('Enter your CNIC number, e.g. 42101-1234567-1.');

            return;
        }

        if (strlen($digits) !== 13) {
            $fail('A CNIC has 13 digits; you entered '.strlen($digits).'.');

            return;
        }

        if (! in_array($digits[0], Pakistan::PROVINCE_CODES, true)) {
            $fail('That CNIC does not start with a valid province code (1-7). Check the first digit.');

            return;
        }

        if (! Pakistan::isValidCnic($digits)) {
            $fail('That is not a valid CNIC number.');

            return;
        }
    }
}
