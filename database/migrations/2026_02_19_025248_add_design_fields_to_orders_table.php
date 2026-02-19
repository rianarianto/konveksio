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
        Schema::table('orders', function (Blueprint $table) {
            // Status desain mockup dari Designer
            $table->enum('design_status', ['pending', 'uploaded', 'approved'])
                ->default('pending')
                ->after('notes');

            // Path file mockup/desain yang diupload Designer
            $table->string('design_image')->nullable()->after('design_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['design_status', 'design_image']);
        });
    }
};
