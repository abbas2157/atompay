<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AtomShop-owned. Read only.
 *
 * Eligibility for AtomPay is the admin's own selection and lives entirely in
 * AtomShop: `products.atompay_eligible`, or `brands.atompay_eligible` for a
 * whole catalogue. AtomPay never writes either - it only shows what admin has
 * already chosen, which is why no new flag was needed to list products here.
 */
class Product extends Model
{
    use BelongsToAtomShop;

    /** Statuses a customer may be shown. "Out of Stock" is shown, but marked. */
    public const VISIBLE = ['Published', 'Out of Stock'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'min_advance_price' => 'integer',
            'atompay_eligible' => 'boolean',
        ];
    }

    /* ----------------------------------------------------------- relations */

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /* -------------------------------------------------------------- scopes */

    /** Published or out of stock; never pending, on hold or closed. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::VISIBLE);
    }

    /**
     * Buyable with AtomPay: flagged itself, or belonging to a brand whose whole
     * catalogue is flagged. One subquery, so this works on a listing without an
     * N+1. Mirrors AtomShop's Product::isAtompayEligible().
     */
    public function scopeAtompayEligible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('atompay_eligible', true)
                ->orWhereIn('brand_id', Brand::query()->atompayEligible()->select('id'));
        });
    }

    /** Admin's own featuring on AtomShop, reused to order AtomPay's listing. */
    public function scopeFeaturedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('feature')->orderByDesc('web_home')->orderByDesc('id');
    }

    /* ---------------------------------------------------------- attributes */

    public function isAtompayEligible(): bool
    {
        return (bool) $this->atompay_eligible
            || ($this->brand_id && Brand::whereKey($this->brand_id)->atompayEligible()->exists());
    }

    public function isInStock(): bool
    {
        return $this->status === 'Published';
    }

    /** Absolute URL of the product picture, served by AtomShop. */
    protected function pictureUrl(): Attribute
    {
        return Attribute::get(fn () => $this->picture
            ? rtrim(config('atompay.asset_url'), '/').'/'.ltrim($this->picture, '/')
            : null);
    }

    /**
     * Canonical product page on the storefront.
     *
     * AtomShop serves a product at the ROOT: `/{slug}` (routes/website.php,
     * name website.product.detail), with brand/{product-slug} and the old
     * catalogue URLs 301ing there. It was `/product/{slug}` here, which is two
     * segments and so never matched that one-segment catch-all - every link
     * built from it 404'd.
     */
    protected function shopUrl(): Attribute
    {
        return Attribute::get(fn () => $this->slug
            ? rtrim(config('atompay.shop_url'), '/').'/'.ltrim($this->slug, '/')
            : rtrim(config('atompay.shop_url'), '/'));
    }
}
