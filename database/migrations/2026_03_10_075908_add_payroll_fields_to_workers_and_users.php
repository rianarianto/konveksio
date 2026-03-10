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
        Schema::table('workers', function (Blueprint $table) {
            $table->string('wage_type')->default('piece_rate')->after('is_active'); // monthly, piece_rate
            $table->integer('base_salary')->default(0)->after('wage_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('wage_type')->default('monthly')->after('role');
            $table->integer('base_salary')->default(0)->after('wage_type');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['wage_type', 'base_salary']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wage_type', 'base_salary']);
        });
    }
};
