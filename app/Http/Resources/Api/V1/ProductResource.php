<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An AtomPay-eligible product card, decorated by CatalogueService with its
 * AtomPay plan. `within_limit` is null when signed out or without a limit.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** Request attribute ProductController sets: what the customer can still spend, or null. */
    public const AVAILABLE = 'atompay.available';

    private bool $withPlan = false;

    /** Detail view: add the month-by-month schedule. */
    public function withPlan(): static
    {
        $this->withPlan = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $plan = $this->atompay_plan;
        $available = $request->attributes->get(self::AVAILABLE);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'brand' => $this->brand?->title,
            'category' => $this->category ? ['id' => $this->category->id, 'title' => $this->category->title] : null,
            'price' => (int) $this->price,
            'monthly' => $plan['monthly'],
            'last_monthly' => $plan['last_monthly'],
            'months' => $plan['months'],
            'image_url' => $this->picture_url,
            'url' => $this->shop_url,
            'in_stock' => $this->isInStock(),
            'within_limit' => $available === null ? null : (int) $this->price <= $available,
            'plan' => $this->when($this->withPlan, fn () => [
                'advance' => 0,
                'markup' => 0,
                'instalments' => array_column($plan['schedule'], 'amount'),
                'total' => $plan['total'],
                'processing_fee_percent' => $plan['fee_percent'],
                'processing_fee' => $plan['processing_fee'],
            ]),
        ];
    }
}
