<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\User;
use App\Support\ShopTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the "Your orders" view: every order, whatever its status, with its
 * schedule, progress and next due instalment - in a fixed number of
 * queries, regardless of how many orders the customer has.
 */
class PaymentScheduleService
{
    /** @return Collection<int, array> one entry per order in any status, newest first */
    public function forUser(User $user): Collection
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['cart.product', 'instalments', 'mirrorInstalments'])
            ->latest('id')
            ->get();

        $delivered = $this->deliveredAt($orders);

        return $orders->map(fn (Order $order) => $this->plan($order, $delivered[$order->id] ?? null));
    }

    /** One of the customer's own orders, whatever its status; null if it is not theirs. */
    public function forOrder(User $user, int $orderId): ?array
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->with(['cart.product', 'instalments', 'mirrorInstalments'])
            ->find($orderId);

        return $order ? $this->plan($order, $this->deliveredAt(collect([$order]))[$order->id] ?? null) : null;
    }

    /** Earliest unpaid monthly instalment across every order, with its order loaded; or null. */
    public function nextDue(User $user): ?OrderInstalment
    {
        $next = OrderInstalment::query()
            ->where('user_id', $user->id)
            ->shopOrders()
            ->monthly()
            ->unpaid()
            ->orderBy('installment_date')
            ->first();

        // A mirrored row's order_id is the custom_orders mirror, not the order.
        $next?->setRelation('order', Order::query()->with('cart.product')->find($next->shop_order_id));

        return $next;
    }

    /**
     * When AtomShop marked each order Delivered, from order_change_histories
     * (the latest Delivered row, as AtomShop's own apps read it). An AtomPay
     * order's status changes are often recorded against its custom_orders
     * mirror, so both are looked up - two queries for any number of orders.
     * Kept once the order moves on to Instalments or Completed; never given
     * for an order not delivered yet, or cancelled.
     *
     * @param Collection<int, Order> $orders
     * @return array<int, Carbon> order id => delivered at
     */
    public function deliveredAt(Collection $orders): array
    {
        $ids = $orders
            ->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Delivered, OrderStatus::Instalments, OrderStatus::Completed], true))
            ->pluck('id')->all();

        if (! $ids) {
            return [];
        }

        $mirrors = CustomOrder::query()->atomPayMirrors()->whereIn('source_order_id', $ids)->pluck('source_order_id', 'id')->all();

        $rows = DB::table('order_change_histories')
            ->where('status', OrderStatus::Delivered->value)
            ->where(fn ($q) => $q
                ->where(fn ($n) => $n->where('order_type', OrderInstalment::ORDER_NORMAL)->whereIn('order_id', $ids))
                ->when($mirrors, fn ($w) => $w->orWhere(fn ($c) => $c
                    ->where('order_type', OrderInstalment::ORDER_CUSTOM)->whereIn('order_id', array_keys($mirrors)))))
            ->get(['order_id', 'order_type', 'created_at']);

        $delivered = [];
        foreach ($rows as $row) {
            $orderId = $row->order_type === OrderInstalment::ORDER_CUSTOM ? ($mirrors[$row->order_id] ?? null) : (int) $row->order_id;
            if (! $orderId || ! $row->created_at) {
                continue;
            }
            $at = ShopTime::of($row->created_at);
            if (! isset($delivered[$orderId]) || $at->gt($delivered[$orderId])) {
                $delivered[$orderId] = $at;
            }
        }

        return $delivered;
    }

    private function plan(Order $order, ?Carbon $deliveredAt = null): array
    {
        $monthly = $order->schedule->where('type', OrderInstalment::TYPE_INSTALMENT)->values();
        $paid    = $monthly->filter->isPaid();

        $paidAmount  = (int) $paid->sum('installment_price');
        $totalAmount = (int) $monthly->sum('installment_price');
        $isActive    = in_array($order->status, OrderStatus::active(), true);
        $hasLate     = $isActive && $monthly->contains->isOverdue();

        return [
            'order'        => $order,
            'delivered_at' => $deliveredAt,
            'product'     => $order->cart?->product,
            'instalments'  => $monthly,
            'paid_count'   => $paid->count(),
            'total_count'  => $monthly->count(),
            'paid_amount'  => $paidAmount,
            'total_amount' => $totalAmount,
            'progress'     => $totalAmount > 0 ? (int) round($paidAmount / $totalAmount * 100) : 0,
            'is_active'    => $isActive,      // still being repaid; what "active plans" counts
            'has_late'     => $hasLate,
            'state'        => $this->state($order, $monthly->count(), $paid->count(), $hasLate),
        ];
    }

    /**
     * One word for where the order stands:
     * pending (awaiting AtomShop's approval) | processing (approved, no schedule yet)
     * | on_track | late | completed | cancelled.
     */
    private function state(Order $order, int $total, int $paid, bool $hasLate): string
    {
        return match (true) {
            $order->status === OrderStatus::Cancelled => 'cancelled',
            in_array($order->status, [OrderStatus::Pending, OrderStatus::Verification], true) => 'pending',
            $hasLate => 'late',
            $order->status === OrderStatus::Completed || ($total > 0 && $paid === $total) => 'completed',
            $total === 0 => 'processing',
            default => 'on_track',
        };
    }
}
