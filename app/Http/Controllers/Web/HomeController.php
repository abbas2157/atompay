<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CatalogueService;
use App\Services\InstalmentQuoteService;
use App\Support\Money;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly InstalmentQuoteService $quotes,
        private readonly CatalogueService $catalogue,
    ) {}

    public function index(): View
    {
        $config = $this->quotes->clientConfig();
        $sample = $this->quotes->quote(
            price: 100000,
            months: in_array(6, $config['tenures'], true) ? 6 : $config['tenures'][0],
        );

        // The v2 design prices AtomPay's own plan (3 months, no advance, 0%
        // markup) rather than seller financing, and works a limit example.
        // v1 ignores these extra variables.
        $income = (int) config('atompay.atompay_plan.example_income');

        return atompay_view('home.index', [
            'calculator' => $config,
            'sample'     => $sample,
            'steps'      => config('atompay.steps'),
            'faqs'       => config('atompay.faqs'),
            // A guest may have estimated before signing in; show it again.
            'estimate'   => session('atompay.estimate'),

            'atompayPlan'   => $this->quotes->atompayPlan(),
            // What admin has marked eligible on AtomShop. v1 ignores it.
            'products'      => design_is_v2() ? $this->catalogue->featured(8) : collect(),
            'productCount'  => design_is_v2() ? $this->catalogue->count() : 0,
            'exampleIncome' => $income,
            'exampleLimit'  => Money::share($income, (float) config('atompay.credit.limit_ratio')),
            'exampleCap'    => Money::share($income, (float) config('atompay.credit.instalment_ratio')),
        ]);
    }

    public function faq(): View
    {
        return atompay_view('pages.faq', ['faqs' => config('atompay.faqs')]);
    }

    // Linked from the app store listings as well as the footer.
    public function privacy(): View
    {
        return atompay_view('pages.privacy', ['support' => config('atompay.api.support')]);
    }
}
