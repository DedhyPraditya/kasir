<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan QRIS, struk, dan tampilan login juga dipisah per toko.
 * Aman dijalankan ulang: kolom yang sudah ada dilewati.
 */
return new class extends Migration
{
    private array $tables = ['qris_settings', 'receipt_settings', 'login_settings'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasColumn($name, 'owner_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->uuid('owner_id')->nullable()->index();
                });
            }
        }

        // Pengaturan lama masuk ke toko default (username "admin", atau admin pertama).
        $adminId = DB::table('users')->where('username', 'admin')->value('id');

        if (! $adminId) {
            $morphKey = config('permission.column_names.model_morph_key', 'model_id');
            $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
            $developerRoleId = DB::table('roles')->where('name', 'developer')->value('id');

            $adminIds = $adminRoleId
                ? DB::table('model_has_roles')->where('role_id', $adminRoleId)->pluck($morphKey)->all()
                : [];
            $developerIds = $developerRoleId
                ? DB::table('model_has_roles')->where('role_id', $developerRoleId)->pluck($morphKey)->all()
                : [];

            $adminId = DB::table('users')
                ->whereIn('id', $adminIds)
                ->whereNotIn('id', $developerIds)
                ->orderBy('created_at')
                ->value('id');
        }

        if ($adminId) {
            foreach ($this->tables as $name) {
                DB::table($name)->whereNull('owner_id')->update(['owner_id' => $adminId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasColumn($name, 'owner_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('owner_id'));
            }
        }
    }
};
