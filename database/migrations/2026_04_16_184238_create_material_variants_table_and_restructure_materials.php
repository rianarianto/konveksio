<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the material_variants table
        Schema::create('material_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('color_name')->nullable();
            $table->string('color_code')->nullable();
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 2)->default(0);
            $table->timestamps();
        });

        // 2. Data Migration: Move existing data to variants
        DB::transaction(function () {
            $materials = DB::table('materials')->get();

            foreach ($materials as $material) {
                // Create a variant for each material
                $variantId = DB::table('material_variants')->insertGetId([
                    'material_id' => $material->id,
                    'color_name' => 'Warna Utama', // Default name for migration
                    'color_code' => $material->color_code,
                    'current_stock' => $material->current_stock,
                    'min_stock' => $material->min_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update OrderItems to point to the new Variant ID
                DB::table('order_items')
                    ->where('bahan_id', $material->id)
                    ->update(['bahan_id' => $variantId]);

                // Update InventoryLog (Stockable) to point to the new Variant ID
                DB::table('inventory_logs')
                    ->where('stockable_type', 'App\\Models\\Material')
                    ->where('stockable_id', $material->id)
                    ->update([
                        'stockable_type' => 'App\\Models\\MaterialVariant',
                        'stockable_id' => $variantId
                    ]);
            }
        });

        // 3. Remove columns from materials table
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['color_code', 'current_stock', 'min_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add columns back to materials
        Schema::table('materials', function (Blueprint $table) {
            $table->string('color_code')->nullable();
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 2)->default(0);
        });

        // 2. Data Migration: Move data back to materials
        DB::transaction(function () {
            $variants = DB::table('material_variants')->get();

            foreach ($variants as $variant) {
                DB::table('materials')
                    ->where('id', $variant->material_id)
                    ->update([
                        'color_code' => $variant->color_code,
                        'current_stock' => $variant->current_stock,
                        'min_stock' => $variant->min_stock,
                    ]);

                // Revert OrderItems
                DB::table('order_items')
                    ->where('bahan_id', $variant->id)
                    ->update(['bahan_id' => $variant->material_id]);

                // Revert InventoryLogs
                DB::table('inventory_logs')
                    ->where('stockable_type', 'App\\Models\\MaterialVariant')
                    ->where('stockable_id', $variant->id)
                    ->update([
                        'stockable_type' => 'App\\Models\\Material',
                        'stockable_id' => $variant->material_id
                    ]);
            }
        });

        // 3. Drop material_variants table
        Schema::dropIfExists('material_variants');
    }
};
