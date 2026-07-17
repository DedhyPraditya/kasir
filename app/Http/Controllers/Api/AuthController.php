<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            if (empty($user->api_token)) {
                $user->api_token = Str::random(80);
                $user->save();
            }

            return response()->json([
                'success' => true,
                'token' => $user->api_token,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah.',
        ], 401);
    }
}
