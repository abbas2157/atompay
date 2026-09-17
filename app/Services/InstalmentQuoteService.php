<?php

namespace App\Services;

use App\DataTransferObjects\InstalmentQuote;
use App\Models\InstallmentCalculator;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Prices an instalment plan exactly the way AtomShop's checkout does
 * (Web\Order\OrderController::checkout_perform):
 *
 *   markup  = per_month% x months x (price - advance)
 *   total   = price + markup
 *   monthly = (total - advance) / months
 *
 * Tenures and the percentage come from AtomShop's installment_calculators
 * row so an admin change there is reflected here within cache_ttl.
 */
class InstalmentQuoteService
{
    private const CACHE_KEY = 'atompay.calculator';

    /** @return array{tenures: int[], per_month: float} */
    public function config(): array
    {
        return Cache::remember(self::CACHE_KEY, config('atompay.plan.cache_ttl'), function () {
            $row = InstallmentCalculator::query()->first();

            return [
                'tenures'   => $row?->tenures() ?? config('atompay.plan.default_tenures'),
                'per_month' => $row?->perMonthPercentage() ?? (float) config('atompay.plan.default_per_month'),
            ];
        });
    }

    /** @return int[] */
    public function tenures(): array
    {
        return $this->config()['tenures'];
    }

    public function perMonthPercentage(): float
    {
        return $this->config()['per_month'];
    }

    /** @return array{min: int, max: int} allowed down payment for a price */
    public function advanceBounds(int $price): array
    {
        return [
            'min' => (int) ceil($price * config('atompay.plan.min_advance_ratio')),
            'max' => (int) floor($price * config('atompay.plan.max_advance_ratio')),
        ];
    }

    /** Lowest permitted down payment - what the landing calculator assumes. */
    public function defaultAdvance(int $price): int
    {
        return $this->advanceBounds($price)['min'];
    }

    /**
     * @throws InvalidArgumentException when the tenure or advance is outside
     *         what AtomShop would accept at checkout.
     */
    public function quote(int $price, int $months, ?int $advance = null): InstalmentQuote
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('Price must be greater than zero.');
        }

        if (! in_array($months, $this->tenures(), true)) {
            throw new InvalidArgumentException('That instalment tenure is not available.');
        }

        $bounds  = $this->advanceBounds($price);
        $advance ??= $bounds['min'];

        if ($advance < $bounds['min'] || $advance > $bounds['max']) {
            throw new InvalidArgumentException(sprintf(
                'Down payment must be between Rs. %s and Rs. %s.',
                number_format($bounds['min']),
                number_format($bounds['max']),
            ));
        }

        $perMonth = $this->perMonthPercentage();
        $financed = $price - $advance;
        $markup   = (int) round(($perMonth * $months / 100) * $financed);
        $total    = $price + $markup;
        $monthly  = (int) ceil(($total - $advance) / $months);

        return new InstalmentQuote(
            price: $price,
            advance: $advance,
            months: $months,
            perMonthPercentage: $perMonth,
            financed: $financed,
            markup: $markup,
            total: $total,
            monthly: $monthly,
        );
    }

    /** Everything the client-side calculator needs to mirror quote(). */
    public function clientConfig(): array
    {
        return [
            'tenures'        => $this->tenures(),
            'perMonth'       => $this->perMonthPercentage(),
            'minAdvance'     => (float) config('atompay.plan.min_advance_ratio'),
            'maxAdvance'     => (float) config('atompay.plan.max_advance_ratio'),
            'price'          => [
                'min'  => (int) config('atompay.plan.price_min'),
                'max'  => (int) config('atompay.plan.price_max'),
                'step' => (int) config('atompay.plan.price_step'),
            ],
        ];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
