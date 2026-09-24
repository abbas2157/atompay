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

    /** Bounds for the calculator controls. */
    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'tenures' => $this->quotes->tenures(),
            'per_month_percentage' => $this->quotes->perMonthPercentage(),
            'advance' => [
                'min_ratio' => (float) config('atompay.plan.min_advance_ratio'),
                'max_ratio' => (float) config('atompay.plan.max_advance_ratio'),
            ],
            'price' => [
                'min' => (int) config('atompay.plan.price_min'),
                'max' => (int) config('atompay.plan.price_max'),
                'step' => (int) config('atompay.plan.price_step'),
            ],
        ]]);
    }

    /** Prices one plan exactly as AtomShop checkout will. */
    public function quote(QuoteRequest $request): JsonResponse
    {
        $price = (int) $request->validated('price');
        $quote = $this->quotes->quote(
            price: $price,
            months: (int) $request->validated('months'),
            advance: $request->filled('advance') ? (int) $request->validated('advance') : null,
        );

        return response()->json(['data' => [
            ...$quote->toArray(),
            'advance_bounds' => $this->quotes->advanceBounds($price),
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
