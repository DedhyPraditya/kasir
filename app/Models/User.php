<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['username', 'password', 'store_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasRoles;

    /** Kunci session: toko yang sedang dilihat developer. */
    public const ACTIVE_STORE_SESSION_KEY = 'active_store_id';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * ID toko yang datanya dilihat user ini.
     * Admin & kasir = toko tempat akunnya terdaftar,
     * developer = toko yang dipilih di sidebar (default: toko paling awal).
     */
    public function tenantId(): ?string
    {
        if ($this->store_id) {
            return $this->store_id;
        }

        if ($this->hasRole('developer')) {
            $active = request()->hasSession() ? session(self::ACTIVE_STORE_SESSION_KEY) : null;

            if ($active && Store::whereKey($active)->exists()) {
                return $active;
            }

            return Store::defaultId();
        }

        return null;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
