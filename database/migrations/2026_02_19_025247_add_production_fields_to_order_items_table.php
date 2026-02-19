<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Kategori produksi: menentukan alur tahapan kerja otomatis
            $table->enum('production_category', ['produksi', 'custom', 'non_produksi', 'jasa'])
                ->default('produksi')
                ->after('price');

            // Detail ukuran & request granular per item
            // Contoh: {"S": 10, "M": 20, "XL": 5, "extras": ["saku", "bordir"]}
            $table->json('size_and_request_details')->nullable()->after('production_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['production_category', 'size_and_request_details']);
        });
    }
};
