<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bulatkan semua data yang ada dulu sebelum ubah tipe kolom
        DB::statement('UPDATE material_variants SET current_stock = ROUND(current_stock, 0), min_stock = ROUND(min_stock, 0)');
        DB::statement('UPDATE product_variants SET stock = ROUND(stock, 0)');

        // Ubah tipe kolom material_variants ke INT signed (bukan unsigned)
        // agar operasi decrement tidak error saat MySQL menghitung ekspresi UNSIGNED
        Schema::table('material_variants', function (Blueprint $table) {
            $table->integer('current_stock')->default(0)->change();
            $table->integer('min_stock')->default(0)->change();
        });

        // Ubah tipe kolom product_variants
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_variants', function (Blueprint $table) {
            $table->decimal('current_stock', 15, 2)->default(0)->change();
            $table->decimal('min_stock', 15, 2)->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('stock', 10, 2)->default(0)->change();
        });
    }
};
