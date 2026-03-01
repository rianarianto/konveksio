<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');                          // Jumlah dibayar
            $table->date('payment_date');                                  // Tanggal pembayaran
            $table->enum('payment_method', ['cash', 'transfer', 'qris'])->default('cash');
            $table->string('note')->nullable();                            // Catatan (DP 1, Pelunasan, dll)
            $table->string('proof_image')->nullable();                     // Path foto bukti transfer
            $table->foreignId('recorded_by')                              // Admin yang input
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
