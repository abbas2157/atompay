<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplicationRequest;
use App\Http\Resources\Api\V1\CreditAssessmentResource;
use App\Services\CreditAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Section 3 of the KYC form: the income & financial profile behind a
 * purchase limit. Like the website, every submission is a new pending
 * assessment and never touches the limit currently in force.
 */
class ApplicationController extends Controller
{
    public function __construct(private readonly CreditAssessmentService $credit) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['kycProfile', 'creditAssessment', 'activeAssessment']);
        $hasProfile = $user->kycProfile !== null;

        return response()->json(['data' => [
            'can_apply' => $hasProfile,
            'requires' => $hasProfile ? null : 'profile',
            // The form pre-fills from `latest`; `active` is the limit in force.
            'latest' => $user->creditAssessment ? new CreditAssessmentResource($user->creditAssessment) : null,
            'active' => $user->activeAssessment ? new CreditAssessmentResource($user->activeAssessment) : null,
        ]]);
    }

    public function store(ApplicationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->kycProfile) {
            throw new BusinessRuleException('profile_required', 'Submit your identity details first, then tell us about your income.');
        }

        $assessment = DB::transaction(fn () => $this->credit->submit(
            $user->fresh(['kycProfile', 'creditAssessment']),
            $request->financialProfile(),
        ));

        return (new CreditAssessmentResource($assessment))->response()->setStatusCode(201);
    }

    /** Every assessment, newest first - how the limit has changed over time. */
    public function history(Request $request): JsonResponse
    {
        return CreditAssessmentResource::collection($request->user()->creditAssessments()->get())->response();
    }
}
