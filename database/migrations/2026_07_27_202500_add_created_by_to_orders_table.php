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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });

        // Set default created_by for existing orders using shop owner/first user
        $firstUser = DB::table('users')->orderBy('id', 'asc')->first();
        if ($firstUser) {
            DB::table('orders')->whereNull('created_by')->update(['created_by' => $firstUser->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
