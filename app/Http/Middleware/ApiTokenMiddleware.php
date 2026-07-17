<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('X-Api-Token') !== env('API_TOKEN')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
