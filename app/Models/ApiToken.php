<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token API aplikasi mobile. Hanya hash SHA-256 yang disimpan; token asli
 * cuma terlihat sekali, saat login.
 */
class ApiToken extends Model
{
    use HasUuids;

    /** Token hangus bila tidak dipakai selama ini (hari). Setiap pemakaian memperpanjangnya. */
    public const LIFETIME_DAYS = 90;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['user_id', 'token', 'last_used_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Buat token baru untuk user; kembalikan token asli (tidak bisa dibaca lagi setelah ini). */
    public static function issueFor(User $user): string
    {
        $plain = Str::random(80);

        static::create([
            'user_id' => $user->id,
            'token' => static::hash($plain),
            'expires_at' => now()->addDays(static::LIFETIME_DAYS),
        ]);

        return $plain;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
