<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Toko/cabang. Semua data (produk, transaksi, pengaturan) dan akun login
 * milik satu toko lewat kolom store_id.
 */
class Store extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'address', 'phone'];

    /** Toko default: toko yang paling awal dibuat. */
    public static function defaultId(): ?string
    {
        return static::query()->orderBy('created_at')->orderBy('id')->value('id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Masih dipakai: punya akun atau data (produk, transaksi, kategori, topping). */
    public function isInUse(): bool
    {
        if ($this->users()->exists()) {
            return true;
        }

        foreach ([Order::class, Product::class, Category::class, Topping::class] as $model) {
            if ($model::withoutGlobalScopes()->where('store_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
