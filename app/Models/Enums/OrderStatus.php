<?php

namespace App\Models\Enums;

enum OrderStatus: string
{
    case Pending      = 'Pending';
    case Verification = 'Varification'; // spelling as stored by AtomShop
    case Processing   = 'Processing';
    case Delivered    = 'Delivered';
    case Instalments  = 'Instalments';
    case Completed    = 'Completed';
    case Cancelled    = 'Cancelled';

    /** Orders that still carry a repayment obligation. */
    public static function active(): array
    {
        return [self::Processing, self::Delivered, self::Instalments];
    }

    public function label(): string
    {
        return $this === self::Verification ? 'Verification' : $this->value;
    }
}
