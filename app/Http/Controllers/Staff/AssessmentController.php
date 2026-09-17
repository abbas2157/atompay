<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressVerificationRequest;
use App\Http\Requests\LimitDecisionRequest;
use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\CreditHistory;
use App\Models\Enums\VerificationStatus;
use App\Services\CreditAssessmentService;
use App\Services\InstalmentQuoteService;
use App\Services\KycService;
use App\Services\RiskScoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff review of one application: Section 2 (address verification) on
 * the KYC profile, Sections 4-5 (risk + decision) on the assessment.
 */
class AssessmentController extends Controller
{
    public function __construct(
        private readonly KycService $kyc,
        private readonly CreditAssessmentService $credit,
        private readonly RiskScoringService $risk,
        private readonly InstalmentQuoteService $quotes,
    ) {}

    public function index(Request $request): View
    {
        $status = AssessmentStatus::tryFrom((string) $request->query('status')) ?? AssessmentStatus::Pending;

        $assessments = CreditAssessment::query()
            ->where('status', $status)
            ->with(['user:id,name,phone,email', 'user.kycProfile:id,user_id,verification_status,submitted_at'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('staff.assessments.index', [
            'assessments' => $assessments,
            'status'      => $status,
            'counts'      => CreditAssessment::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(CreditAssessment $assessment): View
    {
        $assessment->load(['user.kycProfile.city', 'user.kycProfile.verifier:id,name', 'decider:id,name']);
        $user = $assessment->user;

        return view('staff.assessments.show', [
            'assessment' => $assessment,
            'user'       => $user,
            'profile'    => $user->kycProfile,
            'summary'    => $this->credit->summary($user),
            // Live re-score so the reviewer sees the current picture, with reasons.
            'risk'       => $this->risk->assess($user, $assessment, $assessment->credit_history),
            'history'    => $user->creditAssessments()->where('id', '!=', $assessment->id)->get(),
            'tenures'    => $this->quotes->tenures(),
            'options'    => [
                'verification'  => VerificationStatus::options(),
                'creditHistory' => CreditHistory::options(),
                'decision'      => collect(AssessmentStatus::options())->except(AssessmentStatus::Pending->value)->all(),
            ],
        ]);
    }

    /** Section 2. */
    public function verifyAddress(AddressVerificationRequest $request, CreditAssessment $assessment): RedirectResponse
    {
        $profile = $assessment->user->kycProfile ?? abort(422, 'Customer has not submitted KYC yet.');

        $this->kyc->recordAddressVerification($profile, $request->user(), $request->findings(), $request->file('verification_form'));

        return back()->with('status', 'Address verification recorded.');
    }

    /** Sections 4-5. */
    public function decide(LimitDecisionRequest $request, CreditAssessment $assessment): RedirectResponse
    {
        $this->credit->decide($assessment, $request->user(), $request->decision());

        return redirect()->route('staff.assessments.index')
            ->with('status', "Application #{$assessment->id} marked {$assessment->status->label()}.");
    }
}
