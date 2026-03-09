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
            $table->string('position')->after('name')->nullable();
        });

        Schema::dropIfExists('print_positions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('print_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('print_types', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
