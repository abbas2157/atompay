<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVerification extends Model
{
    use BelongsToAtomShop;

    protected function casts(): array
    {
        return [
            'address_found'          => 'boolean',
            'customer_physical_meet' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
