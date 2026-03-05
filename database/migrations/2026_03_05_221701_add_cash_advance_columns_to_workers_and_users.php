<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->unsignedBigInteger('max_cash_advance')->default(0)->after('is_active');
            $table->unsignedBigInteger('current_cash_advance')->default(0)->after('max_cash_advance');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('max_cash_advance')->default(0)->after('role');
            $table->unsignedBigInteger('current_cash_advance')->default(0)->after('max_cash_advance');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['max_cash_advance', 'current_cash_advance']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['max_cash_advance', 'current_cash_advance']);
        });
    }
};
