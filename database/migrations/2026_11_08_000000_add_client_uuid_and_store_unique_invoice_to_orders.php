<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor invoice cukup unik per toko (bukan global), dan order dari aplikasi
 * memakai client_uuid sebagai kunci idempotensi sync offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'client_uuid')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->uuid('client_uuid')->nullable()->after('invoice_number');
            });
        }

        if (Schema::hasIndex('orders', ['invoice_number'], 'unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['invoice_number']));
        }

        if (! Schema::hasIndex('orders', ['store_id', 'invoice_number'], 'unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->unique(['store_id', 'invoice_number']));
        }

        if (! Schema::hasIndex('orders', ['store_id', 'client_uuid'], 'unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->unique(['store_id', 'client_uuid']));
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'client_uuid']);
            $table->dropUnique(['store_id', 'invoice_number']);
            $table->unique('invoice_number');
            $table->dropColumn('client_uuid');
        });
    }
};

