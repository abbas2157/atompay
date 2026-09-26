<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AtomPay-owned. One forgot-password request; see PasswordResetService. */
class PasswordReset extends Model
{
    protected $table = 'atompay_password_resets';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'public_id', 'user_id', 'channel', 'destination', 'code_hash', 'attempts', 'expires_at',
        'verified_at', 'token_hash', 'token_expires_at', 'used_at', 'sent', 'ip',
    ];

    protected $hidden = ['code_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'used_at' => 'datetime',
            'sent' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Still accepting code attempts. */
    public function isOpen(): bool
    {
        return $this->used_at === null
            && $this->verified_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < config('atompay.password_reset.max_attempts');
    }
}
