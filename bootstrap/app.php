<?php

use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
