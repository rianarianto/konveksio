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
        Schema::table('production_tasks', function (Blueprint $table) {
            // Drop the old foreign key that points to users
            $table->dropForeign(['assigned_to']);
            
            // Add the new foreign key that points to workers
            $table->foreign('assigned_to')->references('id')->on('workers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['assigned_to']);
            
            // Revert back to users foreign key
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }
};
