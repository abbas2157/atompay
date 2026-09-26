<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\Pakistan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in AtomShop account, as the app shows it. Read-only here:
 * name, email and phone belong to AtomShop's `users` row.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // No `uuid`: AtomShop's /password/reset/{uuid} link resets the
            // password with nothing else, so the uuid is kept out of the app.
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->shortName(),
            // null for mobile-only sign-ups (their stored email is a placeholder).
            'email' => $this->hasRealEmail() ? $this->email : null,
            'email_verified' => $this->hasRealEmail() && $this->email_verified_at !== null,
            'phone' => $this->phone,
            'phone_formatted' => $this->phone ? Pakistan::formatMobile($this->phone) : null,
            'member_since' => $this->created_at?->toDateString(),
            // not_started | pending | verified | rejected
            'kyc_status' => $this->when($this->relationLoaded('kycProfile'), fn () => KycProfileResource::statusOf($this->kycProfile)),
        ];
    }
}
