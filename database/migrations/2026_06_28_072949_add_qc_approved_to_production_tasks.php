<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tambah kolom qc_approved (null = belum di-QC, true = approved, false = rejected/revisi)
     * dan qc_reviewed_at untuk mencatat waktu review.
     */
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->boolean('qc_approved')->nullable()->default(null)->after('wajib_qc');
            $table->timestamp('qc_reviewed_at')->nullable()->after('qc_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropColumn(['qc_approved', 'qc_reviewed_at']);
        });
    }
};
