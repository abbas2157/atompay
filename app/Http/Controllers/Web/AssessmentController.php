<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentRequest;
use App\Services\CreditAssessmentService;
use Illuminate\Http\RedirectResponse;

/**
 * The landing-page income estimator. It never records a limit: the
 * estimate is kept in session and carried into the full application
 * (Account\ApplicationController) once the visitor is signed in.
 */
class AssessmentController extends Controller
{
    public function __construct(private readonly CreditAssessmentService $credit) {}

    public function store(AssessmentRequest $request): RedirectResponse
    {
        $request->session()->put('atompay.estimate', $this->credit->estimate($request->income()));

        if ($request->user()) {
            return redirect()->route('account.application');
        }

        return redirect()->to(route('home').'#assess');
    }
}
