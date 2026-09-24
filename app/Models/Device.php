<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AtomPay-owned. A phone registered for push, tied to the sign-in that registered it. */
class Device extends Model
{
    protected $table = 'atompay_devices';

    public const PLATFORMS = ['android', 'ios'];

    protected $fillable = ['user_id', 'access_token_id', 'fcm_token', 'fcm_token_hash', 'platform', 'app_version', 'last_seen_at'];

    protected $hidden = ['fcm_token', 'fcm_token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
