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
        return $q->where('status', InstalmentStatus::Unpaid);
    }

    public function scopeMonthly(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_INSTALMENT);
    }

    public function scopeNormalOrders(Builder $q): Builder
    {
        return $q->where('order_type', 'Normal');
    }

    /* ----------------------------------------------------------- relations */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /* ---------------------------------------------------------- attributes */

    public function isPaid(): bool
    {
        return $this->status === InstalmentStatus::Paid;
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
