<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->header('X-Api-Token');

        $token = $plain
            ? ApiToken::with('user')->where('token', ApiToken::hash($plain))->where('expires_at', '>', now())->first()
            : null;

        if (! $token?->user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Perpanjang masa berlaku bila dipakai, maksimal sekali per jam agar tidak menulis DB di setiap request.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subHour())) {
            $token->forceFill([
                'last_used_at' => now(),
                'expires_at' => now()->addDays(ApiToken::LIFETIME_DAYS),
            ])->save();
        }

        // Agar query model otomatis terbatas ke toko user ini.
        Auth::setUser($token->user);

        return $next($request);
    }
}
