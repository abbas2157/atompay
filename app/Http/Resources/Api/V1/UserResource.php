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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'short_name' => $this->shortName(),
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'phone_formatted' => $this->phone ? Pakistan::formatMobile($this->phone) : null,
            'member_since' => $this->created_at?->toDateString(),
            // not_started | pending | verified | rejected
            'kyc_status' => $this->when($this->relationLoaded('kycProfile'), fn () => KycProfileResource::statusOf($this->kycProfile)),
        ];
    }
}
