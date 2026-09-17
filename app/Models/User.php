<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAtomShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * AtomShop's `users` table. AtomPay signs customers in against the same
 * credentials they use on the shop, so there is one account, not two.
 */
class User extends Authenticatable
{
    use Notifiable, SoftDeletes, BelongsToAtomShop;

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------- scopes */

    public function scopeCustomers(Builder $q): Builder
    {
        return $q->where('role', config('atompay.customer_role'));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /* ----------------------------------------------------------- relations */

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(OrderInstalment::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    /** Most recent assessment of any status - what the customer is waiting on. */
    public function creditAssessment(): HasOne
    {
        return $this->hasOne(CreditAssessment::class)->latestOfMany();
    }

    /** Most recent approved/conditional assessment - the limit in force. */
    public function activeAssessment(): HasOne
    {
        return $this->hasOne(CreditAssessment::class)->ofMany(
            ['decided_at' => 'max', 'id' => 'max'],
            fn ($q) => $q->usable(),
        );
    }

    public function creditAssessments(): HasMany
    {
        return $this->hasMany(CreditAssessment::class)->latest('id');
    }

    /* ------------------------------------------------------------ helpers */

    public function isCustomer(): bool
    {
        return $this->role === config('atompay.customer_role') && $this->status === 'active';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, config('atompay.staff_roles'), true) && $this->status === 'active';
    }

    /** "Ayesha K." - enough to greet, not enough to leak. */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        if (count($parts) < 2) {
            return (string) $this->name;
        }

        return $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }
}
