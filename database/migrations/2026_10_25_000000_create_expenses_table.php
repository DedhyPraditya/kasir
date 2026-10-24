<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('category', 50)->default('Operasional');
            $table->string('description');
            $table->date('expense_date')->index();
            $table->string('receipt_image')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
