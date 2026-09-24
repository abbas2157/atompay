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
            // The mobile app has no session and no login page to go back to.
            // Revoking the token is its sign-out: an account blocked after
            // sign-in stops working on its next request, not in 30 days.
            if ($request->is('api/*')) {
                $request->user()?->currentAccessToken()?->delete();
                abort(403, 'Please sign in with an AtomShop customer account.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->withErrors(['login' => 'Please sign in with an AtomShop customer account.']);
        }

        return $next($request);
    }
}
