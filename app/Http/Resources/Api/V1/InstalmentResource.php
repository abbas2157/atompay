<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderInstalment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderInstalment */
class InstalmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'label' => $this->month,                               // AtomShop's own label, e.g. "1st Instalment"
            'due_date' => $this->installment_date?->toDateString(),
            'amount' => $this->installment_price,
            'paid_amount' => $this->installment_paid_price,
            'paid_on' => $this->installment_paid_date?->toDateString(),
            'state' => $this->state,                               // paid | late | due | upcoming
            // Only where the instalment stands alone (dashboard "next due").
            'order_reference' => $this->whenLoaded('order', fn () => $this->order->reference),
            'product_title' => $this->whenLoaded('order', fn () => $this->order->cart?->product?->title),
        ];
    }
}
