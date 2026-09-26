<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AtomPay-owned. One entry in a customer's in-app inbox (and the push that announced it). */
class CustomerNotification extends Model
{
    protected $table = 'atompay_notifications';

    public const TYPE_INSTALMENT_DUE = 'instalment_due';
    public const TYPE_INSTALMENT_OVERDUE = 'instalment_overdue';
    public const TYPE_LIMIT_DECIDED = 'limit_decided';
    public const TYPE_KYC_VERIFIED = 'kyc_verified';
    public const TYPE_KYC_REJECTED = 'kyc_rejected';

    public const TYPE_APPLICATION_RECEIVED = 'application_received';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'dedupe_key', 'pushed_at', 'emailed_at', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'pushed_at' => 'datetime',
            'emailed_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
