<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Toko menjadi entitas sendiri (tabel stores). Kolom owner_id (id akun admin pemilik)
 * diganti store_id. Id toko memakai id admin pemilik lama, jadi data lama tidak perlu dipetakan ulang.
 */
return new class extends Migration
{
    private array $dataTables = [
        'categories', 'products', 'toppings', 'orders',
        'qris_settings', 'receipt_settings', 'login_settings',
    ];

    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        $this->createStoresFromExistingOwners();

        // Indeks lama memakai nama kolom owner_id: lepas dulu sebelum kolom di-rename.
        Schema::table('categories', fn (Blueprint $t) => $t->dropUnique(['owner_id', 'slug']));
        Schema::table('products', fn (Blueprint $t) => $t->dropUnique(['owner_id', 'slug']));

        foreach (array_merge(['users'], $this->dataTables) as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropIndex([ 'owner_id' ]));
            Schema::table($name, fn (Blueprint $t) => $t->renameColumn('owner_id', 'store_id'));
            Schema::table($name, fn (Blueprint $t) => $t->index('store_id'));
        }

        Schema::table('categories', fn (Blueprint $t) => $t->unique(['store_id', 'slug']));
        Schema::table('products', fn (Blueprint $t) => $t->unique(['store_id', 'slug']));

        // Akun admin kini juga anggota tokonya sendiri.
        DB::table('users')
            ->whereNull('store_id')
            ->whereIn('id', DB::table('stores')->select('id'))
            ->update(['store_id' => DB::raw('id')]);
    }

    private function createStoresFromExistingOwners(): void
    {
        $ownerIds = DB::table('users')->whereNotNull('owner_id')->pluck('owner_id');

        foreach ($this->dataTables as $name) {
            $ownerIds = $ownerIds->merge(DB::table($name)->whereNotNull('owner_id')->distinct()->pluck('owner_id'));
        }

        $usedSlugs = [];

        foreach ($ownerIds->unique()->values() as $ownerId) {
            $username = DB::table('users')->where('id', $ownerId)->value('username');
            $receiptName = DB::table('receipt_settings')->where('owner_id', $ownerId)->orderByDesc('id')->value('store_name');

            $name = $receiptName ?: ($username === 'admin' ? 'Nyemil Bebs' : ($username ?: 'Toko'));
            $slug = Str::slug($name) ?: 'toko';
            $base = $slug;
            for ($i = 2; in_array($slug, $usedSlugs, true); $i++) {
                $slug = $base.'-'.$i;
            }
            $usedSlugs[] = $slug;

            DB::table('stores')->insert([
                'id' => $ownerId,
                'name' => $name,
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropUnique(['store_id', 'slug']));
        Schema::table('products', fn (Blueprint $t) => $t->dropUnique(['store_id', 'slug']));

        foreach (array_merge(['users'], $this->dataTables) as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropIndex(['store_id']));
            Schema::table($name, fn (Blueprint $t) => $t->renameColumn('store_id', 'owner_id'));
            Schema::table($name, fn (Blueprint $t) => $t->index('owner_id'));
        }

        Schema::table('categories', fn (Blueprint $t) => $t->unique(['owner_id', 'slug']));
        Schema::table('products', fn (Blueprint $t) => $t->unique(['owner_id', 'slug']));

        Schema::dropIfExists('stores');
    }
};
