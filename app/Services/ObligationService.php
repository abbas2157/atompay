<?php

namespace App\Services;

use App\Models\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** What a customer currently owes AtomShop - read by risk scoring and the dashboard. */
class ObligationService
{
    /**
     * The part of the limit in use ("in use" on the limit card): every unpaid
     * instalment, plus the whole financed amount of AtomPay orders approved
     * but not yet on a schedule. Matches AtomShop admin's
     * AtompayReviewService::exposure(), so both apps show the same figure.
     */
    public function exposure(User $user): int
    {
        $committed = Order::query()
            ->where('user_id', $user->id)
            ->where('type', Order::TYPE_ATOMPAY)
            ->whereIn('status', [OrderStatus::Processing, OrderStatus::Delivered])
            ->get(['total_deal_price', 'advance_price'])
            ->sum(fn (Order $order) => $order->financed_amount);

        return (int) $this->unpaid($user)->sum('installment_price') + (int) $committed;
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
        return OrderInstalment::query()->where('user_id', $user->id)->shopOrders()->unpaid();
    }
}
