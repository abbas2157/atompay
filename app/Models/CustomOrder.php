<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AtomShop keeps an AtomPay order's repayment schedule on a custom_orders
 * "mirror" of it (type = atompay, source_order_id = the orders row), so its
 * order_instalments rows carry the mirror's id with order_type = Custom.
 * AtomPay reads the mirror only to find those rows.
 */
class CustomOrder extends Model
{
    use BelongsToAtomShop;

    public const TYPE_ATOMPAY = 'atompay';

    /** Mirrors of AtomPay orders. */
    public function scopeAtomPayMirrors(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_ATOMPAY)->whereNotNull('source_order_id');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }
}
