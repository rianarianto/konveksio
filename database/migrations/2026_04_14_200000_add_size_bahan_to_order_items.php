<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size')->nullable()->after('production_category');
            $table->unsignedBigInteger('bahan_id')->nullable()->after('size');

            $table->foreign('bahan_id')
                ->references('id')
                ->on('materials')
                ->nullOnDelete();
        });

        // Migrate existing data from JSON to new columns
        \App\Models\OrderItem::query()->each(function ($item) {
            $details = $item->size_and_request_details ?? [];
            $item->update([
                'size' => $details['size'] ?? null,
                'bahan_id' => $details['bahan'] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['bahan_id']);
            $table->dropColumn(['size', 'bahan_id']);
        });
    }
};
