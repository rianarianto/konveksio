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
        Schema::table('print_types', function (Blueprint $table) {
            $table->string('category')->default('jenis')->after('shop_id'); // 'jenis' or 'lokasi'
            $table->dropColumn('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_types', function (Blueprint $table) {
            $table->string('position')->after('name')->nullable();
            $table->dropColumn('category');
        });
    }
};
