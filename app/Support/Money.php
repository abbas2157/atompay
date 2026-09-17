<?php

namespace App\Support;

/**
 * One place that decides how rupees look. Used by the @pkr Blade directive,
 * JSON responses and the Alpine calculator (which mirrors format()).
 */
final class Money
{
    public static function format(int|float|null $amount, string $prefix = 'PKR '): string
    {
        return $prefix.number_format((int) round((float) $amount));
    }

    /** Percentage share of an amount, rounded to whole rupees. */
    public static function share(int|float $amount, float $ratio): int
    {
        return (int) round($amount * $ratio);
    }
}
