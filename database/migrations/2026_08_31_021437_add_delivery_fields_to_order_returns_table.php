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
            if (!Schema::hasColumn('order_returns', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('order_returns', 'delivery_proof')) {
                $table->string('delivery_proof')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('order_returns', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('delivery_proof');
            }
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
