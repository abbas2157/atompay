<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The AtomPay-eligible catalogue, as a browsable page.
 *
 * AtomPay sells nothing here: every card links to the product's own page on
 * Atomshop.pk, where the order is placed. What appears is entirely admin's
 * selection in AtomShop (products.atompay_eligible, or brands.atompay_eligible
 * for a whole catalogue) - AtomPay only reads it.
 *
 * v2 only. In v1 the page sends visitors to the storefront, because v1 has no
 * product listing and inventing one in a design being retired is wasted work.
 */
class ProductController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if (! design_is_v2()) {
            return redirect()->away(config('atompay.shop_url'));
        }

        $filters = [
            'category' => $request->integer('category') ?: null,
            'brand' => $request->integer('brand') ?: null,
        ];

        return atompay_view('pages.products', [
            'products' => $this->catalogue->paginate($filters),
            'categories' => $this->catalogue->categories(),
            'brands' => $this->catalogue->brands(),
            'filters' => $filters,
            'total' => $this->catalogue->count(),
        ]);
    }
}
