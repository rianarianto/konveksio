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
        Schema::table('order_returns', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->string('delivery_proof')->nullable()->after('delivered_at');
            $table->text('delivery_note')->nullable()->after('delivery_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'delivery_proof', 'delivery_note']);
        });
    }
};
