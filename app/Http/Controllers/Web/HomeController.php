<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InstalmentQuoteService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly InstalmentQuoteService $quotes) {}

    public function index(): View
    {
        $config = $this->quotes->clientConfig();
        $sample = $this->quotes->quote(
            price: 100000,
            months: in_array(6, $config['tenures'], true) ? 6 : $config['tenures'][0],
        );

        return view('home.index', [
            'calculator' => $config,
            'sample'     => $sample,
            'steps'      => config('atompay.steps'),
            'faqs'       => config('atompay.faqs'),
            // A guest may have estimated before signing in; show it again.
            'estimate'   => session('atompay.estimate'),
        ]);
    }

    public function faq(): View
    {
        return view('pages.faq', ['faqs' => config('atompay.faqs')]);
    }
}
