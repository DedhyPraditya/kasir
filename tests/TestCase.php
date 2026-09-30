<?php

namespace Tests;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, string> token asli per user id (di DB hanya hash-nya yang tersimpan) */
    private array $plainApiTokens = [];

    /** Beri user sebuah token API dan kembalikan token aslinya. */
    protected function giveApiToken(User $user, ?string $plain = null): string
    {
        $plain ??= Str::random(80);

        ApiToken::create([
            'user_id' => $user->id,
            'token' => ApiToken::hash($plain),
            'expires_at' => now()->addDays(ApiToken::LIFETIME_DAYS),
        ]);

        return $this->plainApiTokens[$user->id] = $plain;
    }

    /** Token asli yang terakhir diberikan lewat giveApiToken() untuk user ini. */
    protected function apiTokenOf(User $user): string
    {
        return $this->plainApiTokens[$user->id];
    }
}
