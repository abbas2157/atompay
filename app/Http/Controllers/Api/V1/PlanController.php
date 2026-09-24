<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanResource;
use App\Services\PaymentScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The customer's AtomShop instalment plans, read from AtomShop's orders. */
class PlanController extends Controller
{
    public function __construct(private readonly PaymentScheduleService $schedule) {}

    /** ?include=completed also lists fully repaid plans. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $withCompleted = $request->query('include') === 'completed';

        return PlanResource::collection($this->schedule->forUser($request->user(), $withCompleted));
    }

    /** One plan with its full schedule. 404 for anyone else's order. */
    public function show(Request $request, int $order): PlanResource
    {
        $plan = $this->schedule->forOrder($request->user(), $order) ?? abort(404);

        return (new PlanResource($plan))->withSchedule();
    }
}
