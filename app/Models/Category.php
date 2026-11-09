<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** AtomShop-owned. Read only. Used to filter the AtomPay-eligible catalogue. */
class Category extends Model
{
    use BelongsToAtomShop;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function pictureUrl(): Attribute
    {
        return Attribute::get(fn () => $this->picture
            ? rtrim(config('atompay.asset_url'), '/').'/'.ltrim($this->picture, '/')
            : null);
    }
}
