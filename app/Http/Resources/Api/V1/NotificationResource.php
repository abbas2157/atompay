<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CustomerNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            // Deep-link payload (screen, ids); always a JSON object. Not called
            // `data`: Laravel would then skip the response's own `data` wrapper.
            'payload' => (object) ($this->data ?? []),
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
