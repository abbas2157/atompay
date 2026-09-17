<?php

namespace App\Models;

use App\Models\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AtomPay-owned. Sections 1-2 of the KYC form: who the customer is, the
 * documents proving it, and the one-time physical address verification.
 */
class KycProfile extends Model
{
    protected $table = 'atompay_kyc_profiles';

    /** Files the customer uploads, keyed by input name => column. */
    public const DOCUMENTS = [
        'cnic_front' => 'cnic_front_path',
        'cnic_back'  => 'cnic_back_path',
        'selfie'     => 'selfie_path',
    ];

    protected $fillable = [
        'user_id', 'full_name', 'cnic', 'mobile', 'date_of_birth', 'residential_address', 'city_id',
        'cnic_front_path', 'cnic_back_path', 'selfie_path', 'face_verified', 'submitted_at',
        'address_verified', 'verification_form_path', 'verified_by', 'verified_at',
        'verification_status', 'verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'       => 'date',
            'face_verified'       => 'boolean',
            'address_verified'    => 'boolean',
            'submitted_at'        => 'datetime',
            'verified_at'         => 'date',
            'verification_status' => VerificationStatus::class,
        ];
    }

    /* ----------------------------------------------------------- relations */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /* ------------------------------------------------------------ helpers */

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    /** All three identity documents are on file. */
    public function hasDocuments(): bool
    {
        return collect(self::DOCUMENTS)->every(fn ($column) => filled($this->{$column}));
    }

    /** "42101-1234567-1" for display; stored as bare digits. */
    protected function cnicFormatted(): Attribute
    {
        return Attribute::get(fn () => preg_replace('/^(\d{5})(\d{7})(\d)$/', '$1-$2-$3', (string) $this->cnic));
    }
}
