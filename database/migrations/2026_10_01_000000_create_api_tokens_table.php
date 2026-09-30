<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Token API aplikasi mobile: satu token per perangkat, disimpan sebagai hash SHA-256
     * dan kedaluwarsa bila tidak dipakai. Token lama di users.api_token dipindah (di-hash)
     * supaya aplikasi yang sudah terpasang tetap bisa dipakai tanpa login ulang.
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        if (Schema::hasColumn('users', 'api_token')) {
            $expires = now()->addDays(90);

            DB::table('users')->whereNotNull('api_token')->orderBy('id')->each(function ($user) use ($expires) {
                DB::table('api_tokens')->insert([
                    'id' => (string) Str::uuid7(),
                    'user_id' => $user->id,
                    'token' => hash('sha256', $user->api_token),
                    'expires_at' => $expires,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['api_token']);
                $table->dropColumn('api_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable()->unique()->after('password');
        });

        Schema::dropIfExists('api_tokens');
    }
};
