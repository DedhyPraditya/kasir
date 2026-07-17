<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (
            $credentials['username'] === env('CASHIER_USERNAME') &&
            $credentials['password'] === env('CASHIER_PASSWORD')
        ) {
            return response()->json([
                'success' => true,
                'token' => env('API_TOKEN'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah.',
        ], 401);
    }
}
