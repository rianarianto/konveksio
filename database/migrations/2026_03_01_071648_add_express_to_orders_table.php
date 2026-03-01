<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Flag express: pesanan prioritas tinggi
            $table->boolean('is_express')->default(false)->after('status');
            // Biaya express (manual, diisi oleh admin)
            $table->unsignedBigInteger('express_fee')->default(0)->after('is_express');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_express', 'express_fee']);
        });
    }
};
