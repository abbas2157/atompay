<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use App\Models\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToAtomShop;

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

    /* ---------------------------------------------------------- attributes */

    /** Amount financed after the down payment, i.e. what instalments repay. */
    protected function financedAmount(): Attribute
    {
        return Attribute::get(fn () => max(0, $this->total_deal_price - $this->advance_price));
    }

    /** Public reference shown to the customer (AtomShop's uuid is internal). */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'AS-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT));
    }
}
