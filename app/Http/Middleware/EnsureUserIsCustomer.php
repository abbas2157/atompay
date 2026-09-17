<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AtomShop's users table also holds sellers, admins and staff. Only
 * active customers get a My AtomPay dashboard.
 */
class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isCustomer()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->withErrors(['login' => 'Please sign in with an AtomShop customer account.']);
        }

        return $next($request);
    }
}
