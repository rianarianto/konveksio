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
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('tax')->default(0)->after('subtotal');
            $table->bigInteger('shipping_cost')->default(0)->after('tax');
            $table->bigInteger('discount')->default(0)->after('shipping_cost');
            $table->bigInteger('down_payment')->default(0)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax', 'shipping_cost', 'discount', 'down_payment']);
        });
    }
};
