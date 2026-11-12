<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One look at a customer's identity by someone other than the customer.
 * Written by KycAccessRecorder; never updated.
 */
class KycAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const ITEMS = [
        'cnic-front' => 'CNIC front',
        'cnic-back' => 'CNIC back',
        'selfie' => 'Selfie',
        'form' => 'Verification form',
        'review' => 'Review page (CNIC number)',
    ];

    protected $table = 'atompay_kyc_access_logs';

    protected $fillable = ['viewer_id', 'subject_user_id', 'kyc_profile_id', 'item', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    public function itemLabel(): string
    {
        return self::ITEMS[$this->item] ?? $this->item;
    }
}
