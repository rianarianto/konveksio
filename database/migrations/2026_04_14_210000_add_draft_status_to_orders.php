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
        // Add 'draft' to enum status. 
        // Note: For MySQL, we usually need to re-define the enum.
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('diterima', 'antrian', 'diproses', 'selesai', 'siap_diambil', 'draft') DEFAULT 'draft'");
        
        // Ensure default is draft for new orders created via the auto-draft system
        DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('diterima', 'antrian', 'diproses', 'selesai', 'siap_diambil') DEFAULT 'diterima'");
    }
};
