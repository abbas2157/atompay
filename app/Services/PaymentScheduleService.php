<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the "All instalments across your orders" view: each active order
 * with its schedule, progress and next due instalment - in two queries,
 * regardless of how many orders the customer has.
 */
class PaymentScheduleService
{
    /** @return Collection<int, array> one entry per order, newest first */
    public function forUser(User $user): Collection
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->active()
            ->with(['cart.product', 'instalments'])
            ->latest('id')
            ->get();

        return $orders->map(fn (Order $order) => $this->plan($order));
    }

    /** Earliest unpaid monthly instalment across every order, or null. */
    public function nextDue(User $user): ?OrderInstalment
    {
        return OrderInstalment::query()
            ->where('user_id', $user->id)
            ->normalOrders()
            ->monthly()
            ->unpaid()
            ->with('order.cart.product')
            ->orderBy('installment_date')
            ->first();
    }

    private function plan(Order $order): array
    {
        $monthly = $order->instalments->where('type', OrderInstalment::TYPE_INSTALMENT)->values();
        $paid    = $monthly->filter->isPaid();

        $paidAmount  = (int) $paid->sum('installment_price');
        $totalAmount = (int) $monthly->sum('installment_price');

        return [
            'order'        => $order,
            'product'      => $order->cart?->product,
            'instalments'  => $monthly,
            'paid_count'   => $paid->count(),
            'total_count'  => $monthly->count(),
            'paid_amount'  => $paidAmount,
            'total_amount' => $totalAmount,
            'progress'     => $totalAmount > 0 ? (int) round($paidAmount / $totalAmount * 100) : 0,
            'has_late'     => $monthly->contains->isOverdue(),
        ];
    }
}
