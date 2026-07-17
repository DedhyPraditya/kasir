<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCashierFromAdminPages
{
    /**
     * Redirect cashiers away from pages reserved for administrators.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('kasir')) {
            return redirect()->route('pos');
        }

        return $next($request);
    }
}

