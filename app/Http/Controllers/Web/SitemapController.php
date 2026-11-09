<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Only public, indexable pages. Account and auth routes are deliberately
 * absent and are also disallowed in robots.txt (RobotsController).
 *
 * <lastmod> is the newest modification time of the files that make up each
 * page - its views plus config/atompay.php, where the FAQ and marketing
 * figures live - so it moves when the content does, not on every request.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly',
                'lastmod' => $this->lastModified([resource_path('views/home'), config_path('atompay.php')]),
            ],
            [
                'loc' => route('faq'), 'priority' => '0.7', 'changefreq' => 'monthly',
                'lastmod' => $this->lastModified([resource_path('views/pages/faq.blade.php'), config_path('atompay.php')]),
            ],
            [
                // The eligible catalogue. Its content changes whenever Atomshop
                // marks another product, so weekly rather than monthly.
                'loc' => route('products'), 'priority' => '0.8', 'changefreq' => 'weekly',
                'lastmod' => $this->lastModified([
                    resource_path('views/v2/pages/products.blade.php'),
                    resource_path('views/components/v2/product-card.blade.php'),
                ]),
            ],
            [
                'loc' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly',
                'lastmod' => $this->lastModified([
                    resource_path('views/pages/privacy.blade.php'),
                    resource_path('views/pages/partials/privacy-body.blade.php'),
                ]),
            ],
            [
                // Public on purpose: the app store listings link straight here,
                // so it must be crawlable and findable without the app.
                'loc' => route('account.delete.info'), 'priority' => '0.3', 'changefreq' => 'yearly',
                'lastmod' => $this->lastModified([
                    resource_path('views/pages/delete-account.blade.php'),
                    resource_path('views/pages/partials/delete-account-body.blade.php'),
                ]),
            ],
        ];

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** @param list<string> $paths files or directories */
    private function lastModified(array $paths): string
    {
        $latest = 0;
        foreach ($paths as $path) {
            $files = is_dir($path) ? glob($path.'/{,*/}*.php', GLOB_BRACE) : [$path];
            foreach ($files as $file) {
                $latest = max($latest, (int) @filemtime($file));
            }
        }

        return Carbon::createFromTimestamp($latest ?: time())->toDateString();
    }
}
