<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Menyimpan ukuran badan anggota tim per pelanggan.
     * Satu pelanggan (customer) bisa punya banyak anggota (B, C, D...).
     * Data ini bisa di-load ulang saat pelanggan memesan lagi.
     */
    public function up(): void
    {
        Schema::create('customer_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // Nama anggota tim
            $table->string('nama');

            // Ukuran badan (semua dalam cm, nullable karena bisa tidak semua diisi)
            $table->decimal('LD', 5, 1)->nullable()->comment('Lebar Dada');
            $table->decimal('PL', 5, 1)->nullable()->comment('Panjang Lengan');
            $table->decimal('LP', 5, 1)->nullable()->comment('Lingkar Pinggang');
            $table->decimal('LB', 5, 1)->nullable()->comment('Lebar Bahu');
            $table->decimal('LPi', 5, 1)->nullable()->comment('Lingkar Pinggul');
            $table->decimal('PB', 5, 1)->nullable()->comment('Panjang Baju');

            // Catatan tambahan (misal: kidal, postur khusus)
            $table->string('catatan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_measurements');
    }
};
