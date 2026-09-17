<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row config AtomShop's admin edits: which tenures are offered and
 * the per-month service percentage. AtomPay reads it, never writes it.
 */
class InstallmentCalculator extends Model
{
    use BelongsToAtomShop;

    /** @return int[] */
    public function tenures(): array
    {
        $raw = json_decode((string) $this->installment_tenure, true) ?: [];
        $tenures = array_values(array_filter(array_map('intval', $raw)));
        sort($tenures);

        return $tenures ?: config('atompay.plan.default_tenures');
    }

    public function perMonthPercentage(): float
    {
        return $this->per_month_percentage > 0
            ? (float) $this->per_month_percentage
            : (float) config('atompay.plan.default_per_month');
    }
}
