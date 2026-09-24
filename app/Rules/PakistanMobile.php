<?php

namespace App\Rules;

use App\Support\Pakistan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Pakistani mobile number. Landlines are rejected on purpose: this number
 * is how we reach the customer about an instalment falling due, so it has to
 * take an SMS.
 */
class PakistanMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            $fail('Enter your mobile number, e.g. 0300 1234567.');

            return;
        }

        if (Pakistan::isValidMobile($digits)) {
            return;
        }

        // Landline area codes (021 Karachi, 042 Lahore, 051 Islamabad ...) all
        // start 0 followed by something other than 3.
        if (preg_match('/^0[1245789]/', $digits)) {
            $fail('That looks like a landline. Enter a mobile number so we can text you about payments.');

            return;
        }

        $fail('Enter a Pakistani mobile number, e.g. 0300 1234567 or +92 300 1234567.');
    }
}
