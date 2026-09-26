<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AtomPay-owned. A customer's settings; a missing row means the defaults. */
class UserPreference extends Model
{
    protected $table = 'atompay_user_preferences';

    public const DEFAULTS = ['email_alerts' => true];

    protected $fillable = ['user_id', 'email_alerts'];

    protected function casts(): array
    {
        return ['email_alerts' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
