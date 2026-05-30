<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            // Multi-tenancy
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Relasi ke order item
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            // Nomor WO (unik)
            $table->string('wo_number')->unique();

            // State Machine Status
            $table->enum('status', [
                'CREATED',
                'QC_PREP',
                'CUTTING',
                'QC_CUT_CHECK',
                'SEWING',
                'QC_SEW_CHECK',
                'DECORATION',
                'BUTTON',
                'FINISHING',
                'QC_FINAL_CHECK',
                'COMPLETED',
            ])->default('CREATED');

            // Tipe dekorasi (hanya relevan saat status DECORATION)
            $table->enum('decoration_type', [
                'bordir_reguler',
                'bordir_3d',
                'sablon_dtf',
                'sablon_manual',
            ])->nullable();

            // Token unik URL (statis, tidak berubah, pengganti login)
            $table->string('token', 64)->unique();

            // Penugasan pekerja per tahap (FK ke workers)
            $table->foreignId('cutting_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('sewing_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('decoration_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('button_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('finishing_worker_id')->nullable()->constrained('workers')->nullOnDelete();

            // Log penolakan QC
            $table->text('reject_reason')->nullable();
            $table->string('reject_proof_image')->nullable();

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
