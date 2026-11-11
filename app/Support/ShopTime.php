<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * AtomShop writes its timestamps (orders.created_at, order_change_histories,
 * ...) as Asia/Karachi wall-clock time, while AtomPay runs in UTC - so
 * Eloquent reads "2026-09-21 15:40:00" as 15:40 UTC, five hours late, and an
 * order delivered at 8 pm shows as the next day. This re-reads the same
 * wall-clock value in the shop's own timezone.
 */
final class ShopTime
{
    public static function of(DateTimeInterface|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $wallClock = $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;

        return Carbon::parse($wallClock, config('atompay.shop_timezone'));
    }

    /** ISO 8601 with the shop's offset, e.g. 2026-09-21T15:40:00+05:00. */
    public static function iso(DateTimeInterface|string|null $value): ?string
    {
        return self::of($value)?->toIso8601String();
    }
}
