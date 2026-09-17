<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use BelongsToAtomShop;

    protected function casts(): array
    {
        return ['verified' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(CustomerVerification::class);
    }
}
