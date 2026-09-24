<?php

/*
|--------------------------------------------------------------------------
| Response security headers
|--------------------------------------------------------------------------
| Applied by App\Http\Middleware\SecurityHeaders to every web response.
| Anything env-driven must live here rather than in the middleware, because
| `php artisan config:cache` in production stops env() working outside config.
*/

return [

    /*
    | Content-Security-Policy. The policy is assembled from these directives,
    | plus a per-request nonce for the inline JSON-LD blocks in the SEO
    | component. Two entries below are deliberate compromises, not oversights:
    |
    |   script-src 'unsafe-eval'   Alpine evaluates x-* attribute expressions
    |                              with the Function constructor. Removing it
    |                              needs Alpine's CSP build, which does not
    |                              support the expressions this app uses.
    |   style-src 'unsafe-inline'  Progress bars set style="width: N%" from
    |                              server data. Style injection is far less
    |                              dangerous than script injection.
    |
    | Turn report_only on first when changing these: the browser then reports
    | violations to the console without breaking the page.
    */
    'csp' => [
        'enabled' => env('SECURITY_CSP_ENABLED', true),
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],   // clickjacking
            'object-src' => ["'none'"],   // no Flash/applets
            'frame-src' => ["'none'"],
            'script-src' => ["'self'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:'],  // AtomShop's asset host is appended
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'manifest-src' => ["'self'"],
        ],
    ],

    /*
    | HTTP Strict Transport Security. Only ever sent over HTTPS in production -
    | sending it in development would pin localhost to https in the browser.
    |
    | `preload` submits the domain to the browser-vendor preload list, which is
    | slow and awkward to undo. Leave it off until HTTPS is settled on every
    | subdomain, then opt in deliberately.
    */
    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => 31536000,   // 1 year
        'include_subdomains' => true,
        'preload' => false,
    ],

    /*
    | Proxies whose X-Forwarded-* headers may be believed. This matters for
    | more than tidiness: rate limiting buckets by client IP, so behind an
    | untrusted proxy every visitor looks like one address and they all share
    | one limit. HSTS also never sends, because the request looks plain HTTP.
    |
    | Empty (the default) means trust nothing, which is correct when Apache
    | serves the site directly. Behind Cloudflare or a load balancer set
    | TRUSTED_PROXIES - to the proxy's addresses if you know them, or '*' if
    | the app is genuinely unreachable except through it. Trusting '*' on a
    | directly reachable host lets anyone spoof their IP with a header.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Request-rate caps, per minute unless noted. Signed-in visitors are keyed
    | by account, so one busy office does not throttle its neighbours; only
    | anonymous traffic shares a bucket by IP. Pakistani mobile carriers put
    | many customers behind one address, so the anonymous ceilings are
    | deliberately generous - tighten them only with real traffic data.
    */
    'rate_limits' => [
        'global' => 600,   // any web request
        'login' => 5,     // per identifier + IP
        'login_ip' => 20,    // per IP, all identifiers
        'register' => 5,     // per hour
        'quote' => 60,    // calculator, read-only
        'assess' => 10,
        'assess_hourly' => 40,
        'application' => 10,
        'documents' => 60,    // KYC file streams
    ],

    /*
    | Browser features this site never uses. Denying them means a successful
    | script injection still cannot reach the camera, microphone or location.
    | The selfie upload is a plain file input, so `camera=()` does not affect
    | it - that opens the OS camera app, not the getUserMedia API.
    */
    'permissions_policy' => [
        'accelerometer', 'autoplay', 'camera', 'display-capture', 'encrypted-media',
        'geolocation', 'gyroscope', 'magnetometer', 'microphone', 'midi',
        'payment', 'usb', 'xr-spatial-tracking',
    ],

];
