<?php

namespace App\Support;

/**
 * Partly hidden email addresses and phone numbers: recognisable to their
 * owner, useless to anyone reading a log or looking over a shoulder.
 */
final class Mask
{
    /** "ay****@gmail.com" / "0300*****21". */
    public static function identifier(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
        }

        return mb_strlen($value) <= 6
            ? str_repeat('*', mb_strlen($value))
            : mb_substr($value, 0, 4).str_repeat('*', mb_strlen($value) - 6).mb_substr($value, -2);
    }
}
