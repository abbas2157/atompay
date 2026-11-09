<?php

use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetDesign;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * The design helpers are also listed in composer.json's "autoload.files", but
 * that list is compiled into vendor/composer/ when the autoloader is dumped - a
 * deploy that pulls the code without running `composer dump-autoload` would not
 * have them, and SecurityHeaders calls design_is_v2() on every single request.
 * Requiring them here means a missed deploy step cannot take the site down.
 * design.php guards each function with function_exists(), so loading it from
 * both places is safe.
 */
require_once __DIR__.'/../app/Support/design.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'customer' => EnsureUserIsCustomer::class,
            'staff' => EnsureUserIsStaff::class,
        ]);

        // Response hardening (CSP, HSTS, framing, referrer) on every page, and
        // a backstop request ceiling. Both are configured in config/security.php.
        // Trusted proxies are set in AppServiceProvider, where config() is safe
        // to read even once `php artisan config:cache` has run.
        // SetDesign runs before SecurityHeaders because the v2 design needs two
        // extra CSP hosts for its web font, and the policy is built on the way out.
        $middleware->web(append: [
            SetDesign::class,
            SecurityHeaders::class,
            'throttle:global',
        ]);
        // Gmail/Yahoo one-click unsubscribe POSTs with no CSRF token (RFC 8058).
        // The route is signed, which is what protects it.
        $middleware->validateCsrfTokens(except: ['email/alerts/*/off']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The mobile app always gets JSON errors, even without an Accept
        // header - never a redirect to /login or an HTML error page.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
