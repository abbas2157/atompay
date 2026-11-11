<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The AtomPay-eligible catalogue, read from AtomShop.
 *
 * AtomPay sells nothing: this is a window onto what admin has already marked
 * buyable with AtomPay (products.atompay_eligible, or brands.atompay_eligible
 * for a whole catalogue), so a customer can see what their limit reaches before
 * they go to the shop. Every card links to the product's page on Atomshop.pk,
 * because the purchase happens there.
 *
 * Each product carries its AtomPay plan - three months, no advance, 0% markup -
 * priced by InstalmentQuoteService so the figure on a card can never disagree
 * with the one at checkout.
 */
class CatalogueService
{
    private const FILTER_CACHE_TTL = 600;

    public function __construct(private readonly InstalmentQuoteService $quotes) {}

    /**
     * A page of eligible products.
     *
     * @param  array{category?: int|null, brand?: int|null, max?: int|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $products = $this->query($filters)
            ->featuredFirst()
            ->paginate($perPage)
            ->withQueryString();

        $this->decorate($products->getCollection());

        return $products;
    }

    /** The handful shown on the landing page. */
    public function featured(int $limit = 8): Collection
    {
        $products = $this->query()->featuredFirst()->limit($limit)->get();

        $this->decorate($products);

        return $products;
    }

    /**
     * What a limit actually reaches: eligible products at or under it. Used to
     * answer "what can I buy with Rs. X" rather than making the customer guess.
     */
    public function withinLimit(int $limit, int $take = 4): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        $products = $this->query(['max' => $limit])->featuredFirst()->limit($take)->get();

        $this->decorate($products);

        return $products;
    }

    /**
     * The cheapest product GET /products would list - the same eligibility
     * filter - for "the lowest-priced eligible product is Rs. X" when nothing
     * fits the limit. Null when nothing is eligible.
     */
    public function lowestPrice(): ?int
    {
        $price = Cache::remember('atompay.catalogue.lowest_price', 600, fn () => $this->query()->min('price'));

        return $price === null ? null : (int) $price;
    }

    public function count(array $filters = []): int
    {
        return $this->query($filters)->count();
    }

    /** Categories that actually have an eligible product, for the filter. */
    public function categories(): Collection
    {
        return Cache::remember('atompay.catalogue.categories', self::FILTER_CACHE_TTL, fn () => Category::query()
            ->active()
            ->whereIn('id', Product::query()->visible()->atompayEligible()->select('category_id'))
            ->orderBy('title')
            ->get(['id', 'title', 'slug']));
    }

    /** Brands that actually have an eligible product, for the filter. */
    public function brands(): Collection
    {
        return Cache::remember('atompay.catalogue.brands', self::FILTER_CACHE_TTL, fn () => Brand::query()
            ->active()
            ->whereIn('id', Product::query()->visible()->atompayEligible()->select('brand_id'))
            ->orderBy('title')
            ->get(['id', 'title', 'slug']));
    }

    /**
     * The category filter for the app, with how many eligible products each has.
     *
     * @return Collection<int, array{id: int, title: string, count: int}>
     */
    public function categoriesWithCounts(): Collection
    {
        return Cache::remember('atompay.catalogue.category_counts', self::FILTER_CACHE_TTL, function () {
            $counts = $this->query()->toBase()->reorder()
                ->selectRaw('category_id, COUNT(*) as aggregate')
                ->groupBy('category_id')
                ->pluck('aggregate', 'category_id');

            return $this->categories()
                ->filter(fn (Category $c) => ($counts[$c->id] ?? 0) > 0)
                ->map(fn (Category $c) => ['id' => $c->id, 'title' => $c->title, 'count' => (int) $counts[$c->id]])
                ->values();
        });
    }

    /** One eligible product with its plan, or null if it is not (or no longer) eligible. */
    public function find(int $id): ?Product
    {
        $product = $this->query()->find($id);

        if ($product) {
            $this->decorate(new Collection([$product]));
        }

        return $product;
    }

    public function forgetFilters(): void
    {
        Cache::forget('atompay.catalogue.categories');
        Cache::forget('atompay.catalogue.brands');
        Cache::forget('atompay.catalogue.category_counts');
        Cache::forget('atompay.catalogue.lowest_price');
    }

    /* ------------------------------------------------------------ internals */

    /** @param array{category?: int|null, brand?: int|null, max?: int|null, q?: string|null, none?: bool} $filters */
    private function query(array $filters = []): Builder
    {
        return Product::query()
            ->visible()
            ->atompayEligible()
            // A product with no price cannot be financed, so it cannot be shown.
            ->where('price', '>', 0)
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($filters['brand'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
            ->when($filters['max'] ?? null, fn (Builder $q, $max) => $q->where('price', '<=', $max))
            // Every word must appear in the title, in any order: "oppo a6" finds "Oppo A6K".
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $q, string $text) {
                foreach (preg_split('/\s+/', $text) as $word) {
                    $q->where('title', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->when($filters['none'] ?? false, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->with(['brand:id,title,slug', 'category:id,title,slug']);
    }

    /** Hangs the AtomPay plan on each product so views do no arithmetic. */
    private function decorate(Collection $products): void
    {
        foreach ($products as $product) {
            $product->atompay_plan = $this->quotes->atompayPlan((int) $product->price);
        }
    }
}
