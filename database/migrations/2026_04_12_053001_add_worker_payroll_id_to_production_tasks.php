<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->foreignId('worker_payroll_id')->nullable()->constrained('worker_payrolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_payroll_id');
        });
    }
};
