<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use BelongsToAtomShop;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)
            ->select(['id', 'pr_number', 'title', 'slug', 'price', 'picture']);
    }
}
