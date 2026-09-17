<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only AtomShop staff roles listed in config('atompay.staff_roles') may review applications. */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isStaff(), 403);

        return $next($request);
    }
}
