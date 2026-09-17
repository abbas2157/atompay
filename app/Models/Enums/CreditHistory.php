<?php

namespace App\Models\Enums;

/** Staff's view of the customer's wider credit record (outside AtomShop). */
enum CreditHistory: string
{
    use HasOptions;

    case None = 'none';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return $this === self::None ? 'No history' : ucfirst($this->value);
    }
}
