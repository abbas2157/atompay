<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InstalmentResource;
use App\Http\Resources\Api\V1\KycProfileResource;
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
            ],
            'stages' => $this->tracker->stages($user->kycProfile, $credit['latest']),
            'next_due' => $nextDue ? new InstalmentResource($nextDue) : null,
            'plans' => [
                'active_count' => $plans->count(),
                'has_late' => $plans->contains('has_late', true),
            ],
            'unread_notifications' => $user->appNotifications()->unread()->count(),
        ]]);
    }
}
