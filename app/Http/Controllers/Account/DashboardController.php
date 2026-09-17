<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\CreditAssessmentService;
use App\Services\PaymentScheduleService;
use App\Services\ProcessTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CreditAssessmentService $credit,
        private readonly PaymentScheduleService $schedule,
        private readonly ProcessTracker $tracker,
    ) {}

    public function index(Request $request): View
    {
        $user   = $request->user()->load(['kycProfile', 'creditAssessment', 'activeAssessment']);
        $credit = $this->credit->summary($user);

        return view('account.dashboard', [
            'user'    => $user,
            'profile' => $user->kycProfile,
            'credit'  => $credit,
            'stages'  => $this->tracker->stages($user->kycProfile, $credit['latest']),
            'plans'   => $this->schedule->forUser($user),
            'nextDue' => $this->schedule->nextDue($user),
        ]);
    }
}
