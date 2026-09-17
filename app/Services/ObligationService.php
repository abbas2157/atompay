<?php

namespace App\Services;

use App\Models\OrderInstalment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** What a customer currently owes AtomShop - read by risk scoring and the dashboard. */
class ObligationService
{
    /** Total unpaid instalments across active orders ("in use" on the limit card). */
    public function exposure(User $user): int
    {
        return (int) $this->unpaid($user)->sum('installment_price');
    }

    /** Instalments falling due within the next month. */
    public function monthlyCommitment(User $user): int
    {
        return (int) $this->unpaid($user)
            ->whereBetween('installment_date', [Carbon::today(), Carbon::today()->addMonth()])
            ->sum('installment_price');
    }

    private function unpaid(User $user): Builder
    {
        return OrderInstalment::query()->where('user_id', $user->id)->normalOrders()->unpaid();
    }
}
