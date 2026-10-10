<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use App\Models\Enums\InstalmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OrderInstalment extends Model
{
    use BelongsToAtomShop;

    public const TYPE_ADVANCE    = 'Advance';
    public const TYPE_INSTALMENT = 'Instalment';

    /** order_type: Normal rows point at orders, Custom rows at custom_orders. */
    public const ORDER_NORMAL = 'Normal';
    public const ORDER_CUSTOM = 'Custom';

    protected function casts(): array
    {
        return [
            'status'                 => InstalmentStatus::class,
            'installment_price'      => 'integer',
            'installment_paid_price' => 'integer',
            'installment_date'       => 'date',
            'installment_paid_date'  => 'date',
        ];
    }

    /* ---------------------------------------------------------------- scopes */

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->where($this->qualifyColumn('status'), InstalmentStatus::Unpaid);
    }

    public function scopeMonthly(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_INSTALMENT);
    }

    public function scopeNormalOrders(Builder $q): Builder
    {
        return $q->where('order_type', self::ORDER_NORMAL);
    }

    /**
     * Rows repaying the customer's AtomShop orders: Normal rows, plus AtomPay
     * orders' rows that AtomShop keeps on their custom_orders mirror.
     */
    public function scopeShopOrders(Builder $q): Builder
    {
        return $q->where(fn (Builder $w) => $w
            ->where('order_type', self::ORDER_NORMAL)
            ->orWhere(fn (Builder $m) => $m
                ->where('order_type', self::ORDER_CUSTOM)
                ->whereIn('order_id', CustomOrder::query()->atomPayMirrors()->select('id'))));
    }

    /* ----------------------------------------------------------- relations */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Only meaningful when isMirrored(); a Normal row's order_id is not a custom_orders id. */
    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class, 'order_id');
    }

    /* ---------------------------------------------------------- attributes */

    public function isPaid(): bool
    {
        return $this->status === InstalmentStatus::Paid;
    }

    /** Kept on an AtomPay order's custom_orders mirror rather than on the order. */
    public function isMirrored(): bool
    {
        return $this->order_type === self::ORDER_CUSTOM;
    }

    /**
     * The orders row this instalment repays. Mirrored rows reached through
     * Order::mirrorInstalments() already carry it as the through key.
     */
    protected function shopOrderId(): Attribute
    {
        return Attribute::get(fn () => $this->isMirrored()
            ? (int) ($this->laravel_through_key ?? $this->customOrder?->source_order_id) ?: null
            : $this->order_id);
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid()
            && $this->installment_date
            && $this->installment_date->lt(Carbon::today());
    }

    public function isDueSoon(int $days = 14): bool
    {
        return ! $this->isPaid()
            && $this->installment_date
            && $this->installment_date->between(Carbon::today(), Carbon::today()->addDays($days));
    }

    /** paid | late | due | upcoming - what the dashboard colours on. */
    protected function state(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->isPaid()    => 'paid',
            $this->isOverdue() => 'late',
            $this->isDueSoon() => 'due',
            default            => 'upcoming',
        });
    }
}
