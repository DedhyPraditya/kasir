<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Scope model ke toko dari user yang sedang login (kolom store_id).
 * User yang belum punya toko tidak melihat data apa pun.
 * Tanpa user login (seeder/console/halaman login), scope tidak diterapkan.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            $tenantId = $user->tenantId();

            $tenantId
                ? $builder->where($builder->getModel()->getTable().'.store_id', $tenantId)
                : $builder->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            $user = Auth::user();

            if (empty($model->store_id) && $user instanceof User) {
                $model->store_id = $user->tenantId();
            }
        });
    }
}
