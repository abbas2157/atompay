<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/** AtomPay-owned. A sign-up waiting for its one-time code; see SignupService. */
class PendingSignup extends Model
{
    use Prunable;

    protected $table = 'atompay_pending_signups';

    public const EMAIL = 'email';
    public const WHATSAPP = 'whatsapp';

    protected $fillable = [
        'public_id', 'name', 'channel', 'email', 'phone', 'password',
        'code_hash', 'code_expires_at', 'sent_at', 'attempts', 'sends',
        'expires_at', 'completed_at', 'user_id', 'ip',
    ];

    protected $hidden = ['password', 'code_hash'];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
            'sends' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Where the code goes: the email address or the 03XXXXXXXXX mobile. */
    public function destination(): string
    {
        return $this->channel === self::EMAIL ? $this->email : $this->phone;
    }

    /** Can still be verified or resent. */
    public function isOpen(): bool
    {
        return $this->completed_at === null
            && $this->expires_at?->isFuture()
            && $this->attempts < config('atompay.signup.max_attempts');
    }

    /**
     * Personal data with no further use: unfinished sign-ups after a day,
     * finished ones (the account now exists) after a week.
     */
    public function prunable(): Builder
    {
        return static::where(fn ($q) => $q
            ->where(fn ($q) => $q->whereNull('completed_at')->where('created_at', '<', now()->subDay()))
            ->orWhere('completed_at', '<', now()->subWeek()));
    }
}
