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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('order')->after('payment_method'); // 'order', 'modal_awal', 'kas_masuk_lain'
        });

        // Backfill shop_id from order
        \DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->update(['payments.shop_id' => \DB::raw('orders.shop_id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
            $table->dropColumn('type');
        });
    }
};
