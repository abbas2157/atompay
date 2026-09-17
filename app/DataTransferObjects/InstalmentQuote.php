<?php

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The result of pricing one plan. Immutable so a quote handed to a view or
 * an API response is exactly what the service computed.
 */
final readonly class InstalmentQuote implements Arrayable
{
    public function __construct(
        public int $price,
        public int $advance,
        public int $months,
        public float $perMonthPercentage,
        public int $financed,
        public int $markup,
        public int $total,
        public int $monthly,
    ) {}

    public function toArray(): array
    {
        return [
            'price'                => $this->price,
            'advance'              => $this->advance,
            'months'               => $this->months,
            'per_month_percentage' => $this->perMonthPercentage,
            'financed'             => $this->financed,
            'markup'               => $this->markup,
            'total'                => $this->total,
            'monthly'              => $this->monthly,
        ];
    }
}
