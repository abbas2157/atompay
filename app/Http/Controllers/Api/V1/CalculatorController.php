<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QuoteRequest;
use App\Http\Requests\AssessmentRequest;
use App\Services\CreditAssessmentService;
use App\Services\InstalmentQuoteService;
use Illuminate\Http\JsonResponse;

/**
 * The public tools: plan calculator and income estimate. Every figure is
 * the server's - the same services the website's calculator and AtomShop's
 * checkout pricing use.
 */
class CalculatorController extends Controller
{
    public function __construct(private readonly InstalmentQuoteService $quotes) {}

    /**
     * Bounds for the calculator controls - AtomPay's plan, so one tenure, no
     * advance and no markup. The shape is the one seller financing used, so an
     * older app build renders it unchanged.
     */
    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'plan' => 'atompay',
            'tenures' => $this->quotes->atompayTenures(),
            'per_month_percentage' => 0.0,
            'advance' => [
                'min_ratio' => 0.0,
                'max_ratio' => 0.0,
            ],
            'processing_fee_percent' => (float) config('atompay.atompay_plan.processing_fee_percent'),
            'price' => [
                'min' => (int) config('atompay.plan.price_min'),
                'max' => (int) config('atompay.plan.price_max'),
                'step' => (int) config('atompay.plan.price_step'),
            ],
        ]]);
    }

    /** Prices AtomPay's plan exactly as AtomShop checkout will charge it. */
    public function quote(QuoteRequest $request): JsonResponse
    {
        $quote = $this->quotes->atompayQuote(
            price: (int) $request->validated('price'),
            months: (int) $request->validated('months'),
            advance: $request->filled('advance') ? (int) $request->validated('advance') : null,
        );

        return response()->json(['data' => [
            ...$quote,
            'advance_bounds' => ['min' => 0, 'max' => 0],
        ]]);
    }

    /** "What could I get?" from income alone. Never recorded - it is not an application. */
    public function estimate(AssessmentRequest $request, CreditAssessmentService $credit): JsonResponse
    {
        $estimate = $credit->estimate($request->income());

        return response()->json(['data' => [
            'monthly_income' => $estimate['monthly_income'],
            'estimated_limit' => $estimate['approved_limit'],
            'estimated_max_instalment' => $estimate['max_instalment'],
        ]]);
    }
}
