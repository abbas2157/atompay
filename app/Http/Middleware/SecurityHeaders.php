<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence-in-depth response headers, configured in config/security.php.
 *
 * None of these replace server-side checks; they limit the damage when
 * something else goes wrong. The CSP in particular is what stops an injected
 * <script> from reading a signed-in customer's CNIC or posting their session
 * somewhere else.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the response renders so Blade can stamp it onto the
        // JSON-LD blocks; @vite picks it up for the bundled assets too.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // Streamed file downloads (KYC documents) set their own cache rules and
        // carry no markup, so a CSP would only add noise.
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');                       // pre-CSP browsers
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($policy = $this->permissionsPolicy()) {
            $headers->set('Permissions-Policy', $policy);
        }

        if ($hsts = $this->strictTransportSecurity($request)) {
            $headers->set('Strict-Transport-Security', $hsts);
        }

        if (config('security.csp.enabled') && $this->isHtml($response)) {
            $headers->set(
                config('security.csp.report_only')
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy',
                $this->contentSecurityPolicy($nonce),
            );
        }

        // Laravel advertises its own version by default; nothing good comes of
        // telling a scanner which framework and version to target.
        $headers->remove('X-Powered-By');

        return $response;
    }

    /** @param string $nonce the per-request nonce for inline JSON-LD */
    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = config('security.csp.directives');

        $directives['script-src'][] = "'nonce-{$nonce}'";

        // Product pictures are served by AtomShop, on a different host.
        if ($host = $this->assetOrigin()) {
            $directives['img-src'][] = $host;
        }

        // `npm run dev` serves assets and the HMR socket from another origin.
        // Without this, a developer running Vite sees a blank, style-less page
        // and a console full of CSP errors.
        if ($dev = $this->viteDevServerOrigin()) {
            $directives['script-src'][] = $dev;
            $directives['style-src'][] = $dev;
            $directives['connect-src'][] = $dev;
            $directives['connect-src'][] = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $dev);
        }

        if (app()->isProduction()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        if ($uri = config('security.csp.report_uri')) {
            $directives['report-uri'] = [$uri];
        }

        return collect($directives)
            ->map(fn (array $values, string $name) => trim($name.' '.implode(' ', $values)))
            ->implode('; ');
    }

    /** The running Vite dev server's origin, or null in production. */
    private function viteDevServerOrigin(): ?string
    {
        if (app()->isProduction() || ! Vite::isRunningHot()) {
            return null;
        }

        $hot = trim((string) @file_get_contents(public_path('hot')));

        return $hot !== '' ? rtrim($hot, '/') : null;
    }

    /** Scheme + host of AtomShop's asset URL, or null when it is same-origin. */
    private function assetOrigin(): ?string
    {
        $url = (string) config('atompay.asset_url');

        if (! $parts = parse_url($url)) {
            return null;
        }

        if (empty($parts['host'])) {
            return null;
        }

        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin === config('app.url') ? null : $origin;
    }

    private function permissionsPolicy(): ?string
    {
        $features = config('security.permissions_policy', []);

        return $features
            ? implode(', ', array_map(fn (string $f) => "{$f}=()", $features))
            : null;
    }

    private function strictTransportSecurity(Request $request): ?string
    {
        if (! config('security.hsts.enabled') || ! app()->isProduction() || ! $request->secure()) {
            return null;
        }

        $value = 'max-age='.config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
