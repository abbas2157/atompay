<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One order's instalment plan, from PaymentScheduleService::plan().
 * The list endpoint leaves out the schedule; the detail endpoint adds it
 * with ->withSchedule().
 */
class PlanResource extends JsonResource
{
    private bool $withSchedule = false;

    public function withSchedule(): static
    {
        $this->withSchedule = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this['order'];
        /** @var Product|null $product */
        $product = $this['product'];
        $instalments = $this['instalments'];

        $nextDue = $instalments->first(fn (OrderInstalment $i) => ! $i->isPaid());

        return [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'ordered_at' => $order->created_at?->toIso8601String(),
                'total_price' => $order->total_deal_price,
                'advance' => $order->advance_price,
                'financed' => $order->financed_amount,
                'tenure' => $order->instalment_tenure,
            ],
            'product' => $product ? [
                'id' => $product->id,
                'title' => $product->title,
                'picture_url' => $product->picture_url,
                'shop_url' => $product->shop_url,
            ] : null,
            'state' => match (true) {
                $this['has_late'] => 'late',
                $this['total_count'] > 0 && $this['paid_count'] === $this['total_count'] => 'completed',
                default => 'on_track',
            },
            'progress' => [
                'paid_count' => $this['paid_count'],
                'total_count' => $this['total_count'],
                'paid_amount' => $this['paid_amount'],
                'total_amount' => $this['total_amount'],
                'remaining_amount' => max(0, $this['total_amount'] - $this['paid_amount']),
                'percent' => $this['progress'],
            ],
            'next_due' => $nextDue ? new InstalmentResource($nextDue) : null,
            'instalments' => $this->when($this->withSchedule, fn () => InstalmentResource::collection($instalments)),
        ];
    }
}
