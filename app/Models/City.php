<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use BelongsToAtomShop;
}
