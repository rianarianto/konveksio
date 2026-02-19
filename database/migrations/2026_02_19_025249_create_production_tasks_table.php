<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('production_tasks', function (Blueprint $table) {
            $table->id();

            // Relasi ke item order (baju apa yang dikerjakan)
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            // Untuk multi-tenancy scope
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Nama tahapan kerja: "Potong", "Jahit", "Kancing", "QC", "Bordir", dll.
            $table->string('stage_name');

            // Karyawan yang mengerjakan (bisa belum ditentukan)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Admin/Designer yang membuat tugas ini (AUDIT TRAIL)
            $table->foreignId('assigned_by')->constrained('users');

            // Upah per unit/baju saat penugasan dibuat (snapshot — tidak berubah walau rate berubah)
            $table->bigInteger('wage_amount')->default(0);

            // Jumlah unit yang dikerjakan di tahap ini
            $table->integer('quantity')->default(0);

            // Status pengerjaan tahap ini
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');

            // Catatan khusus pengerjaan dari Admin/Designer
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_tasks');
    }
};
