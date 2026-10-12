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
        Schema::create('wage_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_task_id')->constrained()->cascadeOnDelete();
            
            // Tarif Lama
            $table->unsignedBigInteger('old_wage_per_pcs')->default(0);
            $table->unsignedBigInteger('old_wage_custom_per_pcs')->nullable();
            $table->unsignedBigInteger('old_wage_amount')->default(0);
            
            // Tarif Baru
            $table->unsignedBigInteger('new_wage_per_pcs')->default(0);
            $table->unsignedBigInteger('new_wage_custom_per_pcs')->nullable();
            $table->unsignedBigInteger('new_wage_amount')->default(0);
            
            // Detail Pengajuan
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            
            // Status & Approval
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wage_correction_requests');
    }
};
