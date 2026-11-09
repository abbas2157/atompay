<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether this request is served the v1 or the v2 design, and puts
 * the answer in config('design.active') for atompay_view() to read.
 *
 * Order of precedence:
 *   1. ?design=v1|v2 - remembered in the session, staff (or any non-production
 *      visitor) only. Lets a reviewer walk the whole site in one design.
 *   2. config('design.default') from .env ATOMPAY_DESIGN.
 *
 * A previewed response is marked noindex: the design that is not live must
 * never be the one a crawler sees.
 */
class SetDesign
{
    public function handle(Request $request, Closure $next): Response
    {
        $default = $this->normalise(config('design.default'));
        $design = $default;
        $previewing = false;

        if ($this->previewAllowed($request)) {
            $queryKey = config('design.preview.query_key', 'design');
            $sessionKey = config('design.preview.session_key', 'atompay.design');

            // ?design=… is sticky, so only the first link needs it.
            if ($request->filled($queryKey) && $this->isAllowed($request->query($queryKey))) {
                $request->session()->put($sessionKey, $request->query($queryKey));
            }

            $chosen = $request->session()->get($sessionKey);
            if ($this->isAllowed($chosen)) {
                $design = $chosen;
                $previewing = $chosen !== $default;
            }
        }

        config(['design.active' => $design]);

        $response = $next($request);

        if ($previewing) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    /**
     * Staff may preview in any environment. Everyone may preview outside
     * production, because staging and local copies are already noindexed.
     */
    private function previewAllowed(Request $request): bool
    {
        if (! config('design.preview.enabled', true) || ! $request->hasSession()) {
            return false;
        }

        return ! app()->isProduction() || (bool) $request->user()?->isStaff();
    }

    private function isAllowed(mixed $design): bool
    {
        return is_string($design) && in_array($design, config('design.allowed', ['v1']), true);
    }

    private function normalise(mixed $design): string
    {
        return $this->isAllowed($design) ? $design : 'v1';
    }
}
