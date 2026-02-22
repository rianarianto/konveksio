<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Hapus kolom dari tabel orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['design_status', 'design_image']);
        });

        // 2. Tambahkan kolom ke tabel order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('design_status', ['pending', 'uploaded', 'approved'])
                  ->default('pending')
                  ->after('price');
            $table->string('design_image')->nullable()->after('design_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            //
        });
    }
};
