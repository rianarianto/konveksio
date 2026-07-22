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
            $table->string('responsibility_type')->default('store_guarantee')->after('status')->comment('store_guarantee, customer_paid');
            $table->decimal('additional_fee', 12, 2)->default(0.00)->after('responsibility_type');
            $table->json('size_breakdown')->nullable()->after('additional_fee');
            $table->string('photo_path')->nullable()->after('size_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn(['responsibility_type', 'additional_fee', 'size_breakdown', 'photo_path']);
        });
    }
};
