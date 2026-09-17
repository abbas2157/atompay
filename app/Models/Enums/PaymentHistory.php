<?php

namespace App\Models\Enums;

/** Derived from the customer's AtomShop order_instalments. */
enum PaymentHistory: string
{
    use HasOptions;

    case None      = 'none';
    case OnTime    = 'on_time';
    case SomeLate  = 'some_late';
    case Defaulted = 'defaulted';

    public function label(): string
    {
        return match ($this) {
            self::None      => 'No previous plans',
            self::OnTime    => 'Always on time',
            self::SomeLate  => 'Some late payments',
            self::Defaulted => 'Overdue / defaulted',
        };
    }
}
