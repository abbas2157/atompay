<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToAtomShop;

    /** Absolute URL of the product picture, served by AtomShop. */
    protected function pictureUrl(): Attribute
    {
        return Attribute::get(fn () => $this->picture
            ? rtrim(config('atompay.asset_url'), '/').'/'.ltrim($this->picture, '/')
            : null);
    }

    /** Canonical product page on the storefront. */
    protected function shopUrl(): Attribute
    {
        return Attribute::get(fn () => rtrim(config('atompay.shop_url'), '/').'/product/'.$this->slug);
    }
}
