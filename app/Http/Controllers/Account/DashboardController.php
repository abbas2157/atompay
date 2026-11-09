<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\CreditAssessmentService;
use App\Services\PaymentScheduleService;
use App\Services\ProcessTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in customer's own pages.
 *
 * The v1 design put all of this on one dashboard. v2 splits it into the three
 * things a customer actually comes here to do - check a limit application, see
 * where verification has got to, and look at what is owed on an order - while
 * `/my` stays the overview that links to them. Every page renders through
 * atompay_view(), so each one falls back to its v1 view until a v2 view exists.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CreditAssessmentService $credit,
        private readonly PaymentScheduleService $schedule,
        private readonly ProcessTracker $tracker,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load(['kycProfile', 'creditAssessment', 'activeAssessment']);
        $credit = $this->credit->summary($user);

        return atompay_view('account.dashboard', [
            'user' => $user,
            'profile' => $user->kycProfile,
            'credit' => $credit,
            'stages' => $this->tracker->stages($user->kycProfile, $credit['latest']),
            'plans' => $this->schedule->forUser($user),
            'nextDue' => $this->schedule->nextDue($user),
        ]);
    }

    /**
     * Splitting the dashboard into three is a v2 idea: the v1 design puts all
     * of it on `/my` on purpose. So in v1 these URLs send the customer there
     * rather than 404 or render an empty shell - and the links to them only
     * appear in v2's own chrome.
     */
    private function v2Only(): ?RedirectResponse
    {
        return design_is_v2() ? null : redirect()->route('account.dashboard');
    }

    /**
     * Every limit application and its decision. A submission never overwrites
     * the limit in force - it opens a new row - so the history here is real,
     * and a rejection shows the reviewer's own note.
     */
    public function applications(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->v2Only()) {
            return $redirect;
        }

        $user = $request->user()->load(['kycProfile', 'creditAssessment', 'activeAssessment']);

        return atompay_view('account.applications', [
            'user' => $user,
            'profile' => $user->kycProfile,
            'credit' => $this->credit->summary($user),
            'assessments' => $user->creditAssessments()->get(),
        ]);
    }

    /** Identity details, the three documents, and the staff address visit. */
    public function verification(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->v2Only()) {
            return $redirect;
        }

        $user = $request->user()->load(['kycProfile.city', 'kycProfile.verifier', 'creditAssessment']);
        $credit = $this->credit->summary($user);

        return atompay_view('account.verification', [
            'user' => $user,
            'profile' => $user->kycProfile,
            'credit' => $credit,
            'stages' => $this->tracker->stages($user->kycProfile, $credit['latest']),
        ]);
    }

    /** AtomShop orders bought against the limit, with each payment schedule. */
    public function orders(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->v2Only()) {
            return $redirect;
        }

        $user = $request->user()->load(['kycProfile', 'activeAssessment']);
        $plans = $this->schedule->forUser($user);

        return atompay_view('account.orders', [
            'user' => $user,
            'credit' => $this->credit->summary($user),
            'plans' => $plans,
            'nextDue' => $this->schedule->nextDue($user),
        ]);
    }
}
