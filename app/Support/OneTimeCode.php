<?php

namespace App\Support;

/**
 * The numeric codes texted and emailed for sign-up and password reset, and
 * how they are stored: an HMAC keyed with APP_KEY, never the code itself, so
 * a database dump does not hand out working codes.
 */
final class OneTimeCode
{
    public static function generate(?int $length = null): string
    {
        $length ??= (int) config('atompay.password_reset.code_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', trim($value), (string) config('app.key'));
    }

    public static function matches(?string $hash, ?string $code): bool
    {
        return $hash !== null && $code !== null && trim($code) !== '' && hash_equals($hash, self::hash($code));
    }
}
