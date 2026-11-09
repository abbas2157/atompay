<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AtomShop-owned. Read only.
 *
 * `atompay_eligible` here is the whole-brand switch: when it is on, every
 * product of the brand can be bought with AtomPay regardless of the product's
 * own flag. Admin sets it in AtomShop admin v2 and it wins over the per-product
 * toggle, which the Brand Partner portal is allowed to set.
 */
class Brand extends Model
{
    use BelongsToAtomShop;

    protected function casts(): array
    {
        return ['atompay_eligible' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Brands whose whole catalogue is AtomPay-eligible. */
    public function scopeAtompayEligible(Builder $query): Builder
    {
        return $query->where('atompay_eligible', true);
    }

    protected function pictureUrl(): Attribute
    {
        return Attribute::get(fn () => $this->picture
            ? rtrim(config('atompay.asset_url'), '/').'/'.ltrim($this->picture, '/')
            : null);
    }
}
