<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Complaint */
class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'topic' => $this->topic,
            'topic_label' => config('atompay.complaints.topics')[$this->topic] ?? $this->topic,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->reference,
            'message' => $this->message,
            'status' => $this->status,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
