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
        Schema::create('payment_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->enum('request_type', ['edit', 'delete'])->default('edit'); // edit or delete
            
            // Old snapshot
            $table->unsignedBigInteger('old_amount');
            $table->string('old_payment_method')->nullable();
            $table->date('old_payment_date')->nullable();
            $table->string('old_note')->nullable();
            $table->string('old_proof_image')->nullable();
            
            // Proposed changes (nullable if delete)
            $table->unsignedBigInteger('new_amount')->nullable();
            $table->string('new_payment_method')->nullable();
            $table->date('new_payment_date')->nullable();
            $table->string('new_note')->nullable();
            $table->string('new_proof_image')->nullable();
            
            // Request meta
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            
            // Approval status
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
        Schema::dropIfExists('payment_correction_requests');
    }
};
