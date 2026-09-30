<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Batas salah password per username+IP sebelum dikunci sementara. */
    private const MAX_ATTEMPTS = 5;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'api-login|'.Str::lower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429)->header('Retry-After', $seconds);
        }

        $user = User::where('username', $credentials['username'])->first();

        // Hash::check tetap dijalankan bila user tidak ada, supaya waktu respons tidak membocorkan username.
        $valid = Hash::check($credentials['password'], $user?->password ?? Hash::make(Str::random(16)));

        if ($user && $valid) {
            RateLimiter::clear($throttleKey);

            return response()->json([
                'success' => true,
                'token' => ApiToken::issueFor($user),
                'user' => [
                    'username' => $user->username,
                    'role' => $user->hasRole('kasir') ? 'kasir' : 'admin',
                ],
            ]);
        }

        RateLimiter::hit($throttleKey, 300);

        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah.',
        ], 401);
    }
}
