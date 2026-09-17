<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Services\InstalmentQuoteService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Server-side quote for anything that must not trust client math
 * (mobile app, checkout hand-off, support tools).
 */
class QuoteController extends Controller
{
    public function __construct(private readonly InstalmentQuoteService $quotes) {}

    public function __invoke(QuoteRequest $request): JsonResponse
    {
        try {
            $quote = $this->quotes->quote(
                price: (int) $request->validated('price'),
                months: (int) $request->validated('months'),
                advance: $request->filled('advance') ? (int) $request->validated('advance') : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'quote' => $quote->toArray()]);
    }
}
