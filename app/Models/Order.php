<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use App\Models\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Order extends Model
{
    use BelongsToAtomShop;

    /** `type` of an order financed by AtomPay. */
    public const TYPE_ATOMPAY = 'atompay';

    protected function casts(): array
    {
        return [
            'status'            => OrderStatus::class,
            'total_deal_price'  => 'integer',
            'advance_price'     => 'integer',
            'instalment_tenure' => 'integer',
        ];
    }

    /* ---------------------------------------------------------------- scopes */

    /** Orders that still have money owed on them. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', OrderStatus::active());
    }

    /* ----------------------------------------------------------- relations */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(OrderInstalment::class)
            ->where('order_type', 'Normal')
            ->orderBy('installment_date');
    }

    /** An AtomPay order's schedule, which AtomShop keeps on its custom_orders mirror. */
    public function mirrorInstalments(): HasManyThrough
    {
        return $this->hasManyThrough(OrderInstalment::class, CustomOrder::class, 'source_order_id', 'order_id')
            ->where('custom_orders.type', CustomOrder::TYPE_ATOMPAY)
            ->where('order_instalments.order_type', OrderInstalment::ORDER_CUSTOM)
            ->orderBy('installment_date');
    }

    /* ---------------------------------------------------------- attributes */

    /** Amount financed after the down payment, i.e. what instalments repay. */
    protected function financedAmount(): Attribute
    {
        return Attribute::get(fn () => max(0, $this->total_deal_price - $this->advance_price));
    }

    /**
     * Every row of the repayment schedule, wherever AtomShop keeps it.
     * Eager-load both `instalments` and `mirrorInstalments` when listing.
     *
     * @return Collection<int, OrderInstalment>
     */
    protected function schedule(): Attribute
    {
        return Attribute::get(fn () => $this->instalments
            ->concat($this->mirrorInstalments)
            ->sortBy(fn (OrderInstalment $i) => $i->installment_date?->timestamp)
            ->values());
    }

    /** Public reference shown to the customer (AtomShop's uuid is internal). */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'AS-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT));
    }
}
