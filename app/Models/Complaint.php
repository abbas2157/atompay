<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AtomPay-owned. A complaint a customer raised in the app, and our reply. */
class Complaint extends Model
{
    protected $table = 'atompay_complaints';

    public const STATUSES = ['open', 'answered', 'closed'];

    protected $fillable = ['user_id', 'topic', 'order_id', 'message', 'status', 'reply', 'replied_at'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime', 'order_id' => 'integer'];
    }

    /** "CMP-1042": what the customer quotes to support. Derived from the id, so it never collides. */
    public static function referenceFor(int $id): string
    {
        return 'CMP-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
