<?php

use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        $middleware->web(append: [
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
