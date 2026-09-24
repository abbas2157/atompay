<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signed-in device (one access token), for the "where you're signed in"
 * list. Never includes the token itself.
 *
 * @mixin PersonalAccessToken
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'current' => $request->user()?->currentAccessToken()?->getKey() === $this->id,
            'signed_in_at' => $this->created_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
