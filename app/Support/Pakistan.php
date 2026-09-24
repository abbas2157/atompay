<?php

namespace App\Support;

/**
 * Pakistani CNIC and mobile number handling, in one place so the form
 * request, the model accessors and the browser all agree on what a valid
 * number is and how it should look.
 *
 * Storage is always the bare canonical form - 13 digits for a CNIC,
 * 03XXXXXXXXX for a mobile - because that is what AtomShop's `users`.phone
 * and `customers`.cnic_no mostly hold, and because two customers writing
 * the same number differently must not become two different records.
 * Dashes and spaces are presentation, added on the way out.
 */
final class Pakistan
{
    /**
     * First digit of a CNIC is the province/region that issued it:
     * 1 KP, 2 FATA, 3 Punjab, 4 Sindh, 5 Balochistan, 6 Islamabad,
     * 7 Gilgit-Baltistan. Anything else was never issued by NADRA.
     */
    public const PROVINCE_CODES = ['1', '2', '3', '4', '5', '6', '7'];

    /* ------------------------------------------------------------------ CNIC */

    /** Digits only, e.g. "42101-1234567-1" -> "4210112345671". */
    public static function normalizeCnic(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    /** "4210112345671" -> "42101-1234567-1"; anything else is returned unchanged. */
    public static function formatCnic(?string $value): string
    {
        $digits = self::normalizeCnic($value);

        return preg_match('/^\d{13}$/', $digits)
            ? substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12)
            : (string) $value;
    }

    public static function isValidCnic(?string $value): bool
    {
        $digits = self::normalizeCnic($value);

        return strlen($digits) === 13
            && in_array($digits[0], self::PROVINCE_CODES, true)
            && trim($digits, '0') !== '';   // 0000000000000 is 13 digits and still not a CNIC
    }

    /* ---------------------------------------------------------------- mobile */

    /**
     * Every shape a Pakistani customer might type, reduced to 03XXXXXXXXX:
     *
     *   0300 1234567      +92 300 1234567      0092-300-1234567
     *   03001234567       +923001234567        923001234567       3001234567
     *
     * Returns '' when the input cannot be read as a Pakistani mobile, so the
     * validation rule - not this method - is what reports the problem.
     */
    public static function normalizeMobile(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        // Strip the country code in any of the ways it gets written.
        foreach (['0092', '92'] as $prefix) {
            if (str_starts_with($digits, $prefix) && strlen($digits) > strlen($prefix)) {
                $digits = substr($digits, strlen($prefix));
                break;
            }
        }

        // A bare subscriber number (3001234567) is missing its trunk zero.
        if (preg_match('/^3\d{9}$/', $digits)) {
            $digits = '0'.$digits;
        }

        return preg_match('/^03\d{9}$/', $digits) ? $digits : '';
    }

    /** "03001234567" -> "0300 1234567". */
    public static function formatMobile(?string $value): string
    {
        $digits = self::normalizeMobile($value);

        return $digits === '' ? (string) $value : substr($digits, 0, 4).' '.substr($digits, 4);
    }

    public static function isValidMobile(?string $value): bool
    {
        return self::normalizeMobile($value) !== '';
    }
}
