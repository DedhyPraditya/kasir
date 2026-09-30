<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabel data toko: dipisah per akun admin lewat kolom owner_id. */
    private array $tenantTables = [
        'categories', 'products', 'toppings', 'orders',
        'qris_settings', 'receipt_settings', 'login_settings',
    ];

    public function up(): void
    {
        // users.owner_id: null = admin/developer, terisi = kasir milik admin (toko) tsb.
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('owner_id')->nullable()->index();
        });

        foreach ($this->tenantTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('owner_id')->nullable()->index();
            });
        }

        // slug unik per toko, bukan global
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['owner_id', 'slug']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['owner_id', 'slug']);
        });

        $this->backfillToDefaultStore();
    }

    /** Data & kasir lama masuk ke toko admin default (username "admin", atau admin pertama). */
    private function backfillToDefaultStore(): void
    {
        $developerIds = $this->userIdsWithRole('developer');

        $adminId = DB::table('users')->where('username', 'admin')->value('id');

        if (! $adminId) {
            $adminId = DB::table('users')
                ->whereIn('id', $this->userIdsWithRole('admin'))
                ->whereNotIn('id', $developerIds)
                ->orderBy('created_at')
                ->value('id');
        }

        if (! $adminId) {
            return;
        }

        foreach ($this->tenantTables as $name) {
            DB::table($name)->whereNull('owner_id')->update(['owner_id' => $adminId]);
        }

        DB::table('users')
            ->whereIn('id', $this->userIdsWithRole('kasir'))
            ->whereNull('owner_id')
            ->update(['owner_id' => $adminId]);
    }

    /** @return array<int, string> */
    private function userIdsWithRole(string $role): array
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if (! $roleId) {
            return [];
        }

        return DB::table('model_has_roles')->where('role_id', $roleId)->pluck('model_id')->all();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'slug']);
            $table->unique('slug');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'slug']);
            $table->unique('slug');
        });

        foreach ($this->tenantTables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('owner_id'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('owner_id'));
    }
};
