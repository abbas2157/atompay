<?php

namespace App\Services;

use App\Models\Enums\OrderStatus;
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
    /**
     * @param bool $withCompleted also list fully repaid orders (the app's "history" tab)
     * @return Collection<int, array> one entry per order, newest first
     */
    public function forUser(User $user, bool $withCompleted = false): Collection
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->when(
                $withCompleted,
                fn ($q) => $q->whereIn('status', [...OrderStatus::active(), OrderStatus::Completed]),
                fn ($q) => $q->active(),
            )
            ->with(['cart.product', 'instalments'])
            ->latest('id')
            ->get();

        return $orders->map(fn (Order $order) => $this->plan($order));
    }

    /** One of the customer's own orders, whatever its status; null if it is not theirs. */
    public function forOrder(User $user, int $orderId): ?array
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->with(['cart.product', 'instalments'])
            ->find($orderId);

        return $order ? $this->plan($order) : null;
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
