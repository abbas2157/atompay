<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogueService;
use App\Services\CreditAssessmentService;
use App\Services\InstalmentQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The AtomPay-eligible catalogue for the Shop tab, and the plan preview for
 * any price. Public: signing in only adds "does it fit my limit?".
 *
 * Every figure is AtomPay's own plan (3 months, no advance, 0% markup) from
 * InstalmentQuoteService::atompayPlan - never POST /quote, which prices
 * Atomshop seller financing.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogueService $catalogue,
        private readonly InstalmentQuoteService $quotes,
        private readonly CreditAssessmentService $credit,
    ) {}

    /** GET /products?category=&q=&within_limit=1&page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
            'within_limit' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $available = $this->available($request);
        $withinLimit = $request->boolean('within_limit');

        $products = $this->catalogue->paginate([
            'category' => $filters['category'] ?? null,
            'q' => $filters['q'] ?? null,
            'max' => $withinLimit ? ($available ?? 0) : null,
            // "Within my limit" with no limit (or nothing left) is an empty list, not everything.
            'none' => $withinLimit && ! $available,
        ], 20);

        return ProductResource::collection($products);
    }

    /** Categories that have at least one eligible product, with how many. */
    public function categories(): JsonResponse
    {
        return response()->json(['data' => $this->catalogue->categoriesWithCounts()]);
    }

    /** One eligible product. 404 if it is not eligible (or not visible) any more. */
    public function show(Request $request, int $product): ProductResource
    {
        $this->available($request);
        $found = $this->catalogue->find($product) ?? abort(404, 'This product is not available with AtomPay.');

        return (new ProductResource($found))->withPlan();
    }

    /** GET /plan-preview?price= : AtomPay's plan for any price. */
    public function preview(Request $request): JsonResponse
    {
        $price = (int) $request->validate([
            'price' => ['required', 'integer', 'min:1', 'max:10000000'],
        ])['price'];

        $plan = $this->quotes->atompayPlan($price);
        $available = $this->available($request);

        return response()->json(['data' => [
            'price' => $plan['price'],
            'advance' => 0,
            'markup' => 0,
            'months' => $plan['months'],
            'instalments' => array_column($plan['schedule'], 'amount'),
            'total' => $plan['total'],
            'processing_fee_percent' => $plan['fee_percent'],
            'processing_fee' => $plan['processing_fee'],
            'fits_limit' => $available === null ? null : $price <= $available,
        ]]);
    }

    /**
     * What the signed-in customer can still spend, or null when signed out
     * or without a limit. Optional auth: a bad token is treated as signed out
     * here, because nothing private is returned.
     */
    private function available(Request $request): ?int
    {
        if ($request->attributes->has(ProductResource::AVAILABLE)) {
            return $request->attributes->get(ProductResource::AVAILABLE);
        }

        $user = $request->user('sanctum');
        $available = null;

        if ($user instanceof User && $user->isCustomer()) {
            $summary = $this->credit->summary($user->loadMissing(['creditAssessment', 'activeAssessment']));
            $available = $summary['active'] ? (int) $summary['available'] : null;
        }

        // ProductResource reads it to answer `within_limit` on each card.
        $request->attributes->set(ProductResource::AVAILABLE, $available);

        return $available;
    }
}
