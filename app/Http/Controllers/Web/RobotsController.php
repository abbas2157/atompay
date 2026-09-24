<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * robots.txt, generated rather than a static file in public/, because:
 *
 *  - the Sitemap line must be an absolute URL, and only the app knows
 *    whether it is atompay.shop or a local / staging copy;
 *  - anything that is not production must never be indexed, so a staging
 *    site that leaks onto the internet does not compete with the real one.
 *
 * Crawlers only read /robots.txt at the domain root, so locally (served from
 * /atompay) this is for checking the output, not for crawlers.
 */
class RobotsController extends Controller
{
    /**
     * Private areas. Most are also noindex or behind sign-in; listing them
     * keeps crawlers from spending their budget on redirects to /login.
     */
    private const DISALLOW = [
        '/my',          // customer dashboard + application
        '/staff',       // review queue
        '/documents',   // KYC files (owner/staff only)
        '/login',
        '/register',
        '/logout',
        '/quote',       // POST-only tools
        '/assess',
        '/api/',        // mobile app API
        '/up',          // health check
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->isProduction()) {
            $lines[] = 'Allow: /';
            foreach (self::DISALLOW as $path) {
                $lines[] = "Disallow: {$path}";
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        } else {
            $lines[] = 'Disallow: /';   // local / staging: keep out of every index
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
