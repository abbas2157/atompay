<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Only public, indexable pages. Account and auth routes are deliberately
 * absent and are also disallowed in robots.txt.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('faq'),  'priority' => '0.7', 'changefreq' => 'monthly'],
        ];

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
