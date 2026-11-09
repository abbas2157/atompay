<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\KycApplicationRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Services\CreditAssessmentService;
use App\Services\KycService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The customer-facing AtomPay application: Section 1 (identity) and
 * Section 3 (financial profile). Sections 2, 4 and 5 are staff work.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private readonly KycService $kyc,
        private readonly CreditAssessmentService $credit,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user();

        $profile = $this->kyc->profileFor($user);

        return atompay_view('account.application', [
            'profile'    => $profile,
            'latest'     => $user->creditAssessment,
            // The limit in force; when set, the page is a request for a limit review.
            'active'     => $user->activeAssessment,
            'cities'     => City::query()->where('status', 'active')->orderBy('title')->get(['id', 'title']),
            'employment' => EmploymentStatus::options(),
            'sources'    => IncomeSource::options(),
            // Only the chosen city's areas: 2,300+ in total is too many to ship,
            // and the picker reloads them from areas() when the city changes.
            'areas'      => $this->areasOf((int) old('city_id', $profile->city_id)),
            // Income typed into the landing-page estimator, if any.
            'prefill'    => ['monthly_income' => session('atompay.estimate.monthly_income')],
        ]);
    }

    /** The areas of one city, for the application form's dependent picker. */
    public function areas(City $city): JsonResponse
    {
        return response()->json(
            $this->areasOf($city->id)->map(fn (Area $area) => ['id' => $area->id, 'title' => $area->title])->values()
        );
    }

    /** @return \Illuminate\Support\Collection<int, Area> */
    private function areasOf(?int $cityId)
    {
        return $cityId
            ? Area::query()->where('city_id', $cityId)->where('status', 'active')
                ->orderBy('title')->get(['id', 'title'])
            : collect();
    }

    public function store(KycApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->kyc->wouldChange($user, $request->identity(), $request->documents())
            && $this->credit->isUnchangedReview($user, $request->financialProfile())) {
            throw ValidationException::withMessages(['monthly_income' => CreditAssessmentService::UNCHANGED_REVIEW]);
        }

        DB::transaction(function () use ($request, $user) {
            $this->kyc->submit($user, $request->identity(), $request->documents());
            $this->credit->submit($user->fresh(['kycProfile', 'creditAssessment']), $request->financialProfile());
        });

        $request->session()->forget('atompay.estimate');

        return redirect()->route('account.dashboard')->with('status', $user->activeAssessment
            ? 'Limit review requested. Your current limit stays active until our team decides.'
            : 'Application received. Our team will verify your address and confirm your limit.');
    }
}
