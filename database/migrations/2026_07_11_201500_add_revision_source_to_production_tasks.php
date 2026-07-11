<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            // 'qc_review' = berasal dari QC_REVIEW per-stage
            // 'qc_akhir'  = berasal dari QC Akhir
            $table->string('revision_source')->nullable()->after('is_revision');
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropColumn('revision_source');
        });
    }
};
