<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InstalmentResource;
use App\Http\Resources\Api\V1\KycProfileResource;
use App\Models\OrderInstalment;
use App\Services\CatalogueService;
use App\Services\CreditAssessmentService;
use App\Services\PaymentScheduleService;
use App\Services\ProcessTracker;
use App\Services\StatusBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The home screen in one request - the same figures as the web dashboard. */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CreditAssessmentService $credit,
        private readonly PaymentScheduleService $schedule,
        private readonly ProcessTracker $tracker,
        private readonly StatusBanner $banner,
        private readonly CatalogueService $catalogue,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['kycProfile', 'creditAssessment', 'activeAssessment']);
        $credit = $this->credit->summary($user);
        $plans = $this->schedule->forUser($user);
        $nextDue = $this->schedule->nextDue($user);

        return response()->json(['data' => [
            'kyc_status' => KycProfileResource::statusOf($user->kycProfile),
            'application_status' => $credit['latest']?->status->value,
            'banner' => $this->banner->for($user->kycProfile, $credit['latest']),
            'limit' => [
                'has_limit' => $credit['active'] !== null,
                'status' => $credit['active']?->status->value,     // approved | conditional | null
                'approved' => $credit['limit'],
                'used' => $credit['used'],
                'available' => $credit['available'],
                'used_percent' => $credit['used_percent'],
                'max_instalment' => $credit['max_instalment'],
                'tenure' => $credit['tenure'],
                'available_after_next' => $this->availableAfterNext($credit, $nextDue),
            ],
            // "Nothing fits Rs. X - the lowest-priced eligible product is Rs. Y."
            'lowest_eligible_price' => $credit['active'] !== null ? $this->catalogue->lowestPrice() : null,
            'stages' => $this->tracker->stages($user->kycProfile, $credit['latest']),
            'next_due' => $nextDue ? new InstalmentResource($nextDue) : null,
            'plans' => [
                'active_count' => $plans->where('is_active', true)->count(),
                'pending_count' => $plans->where('state', 'pending')->count(),
                'has_late' => $plans->contains('has_late', true),
            ],
            'unread_notifications' => $user->appNotifications()->unread()->count(),
        ]]);
    }

    /**
     * What `limit.available` becomes once next_due is paid in full - "after
     * 10 Jan you will have Rs. X to spend". Worked out the way `used` is: an
     * unpaid instalment counts in full until it is marked Paid, so paying it
     * frees its whole amount. That equals the app's
     * min(approved, available + (amount - paid_amount)) whenever nothing is
     * part-paid and the limit is not over-used; where they differ, this is
     * the figure the dashboard will actually show afterwards.
     *
     * @param array{active: mixed, limit: int, used: int} $credit
     */
    private function availableAfterNext(array $credit, ?OrderInstalment $nextDue): ?int
    {
        if ($credit['active'] === null || $nextDue === null) {
            return null;
        }

        $usedAfter = max(0, $credit['used'] - (int) $nextDue->installment_price);

        return min($credit['limit'], max(0, $credit['limit'] - $usedAfter));
    }
}
