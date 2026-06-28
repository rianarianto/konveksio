<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Change status from ENUM to VARCHAR(50)
        DB::statement("ALTER TABLE `work_orders` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'CREATED'");

        Schema::table('work_orders', function (Blueprint $table) {
            // Task 1: Dynamic stage sequence
            $table->json('stage_sequence')->nullable()->after('status');
            $table->unsignedInteger('current_stage_index')->default(0)->after('stage_sequence');

            // Task 2: Dynamic QC review
            $table->string('current_review_stage', 50)->nullable()->after('current_stage_index');

            // Task 3 + 5: Queue & express priority
            $table->timestamp('stage_entered_at')->nullable()->after('current_review_stage');
            $table->boolean('is_express')->default(false)->after('stage_entered_at');
        });

        // Backfill existing WOs: set default stage_sequence from current status
        $wos = DB::table('work_orders')->get();
        foreach ($wos as $wo) {
            $defaultSequence = ['Potong', 'Jahit', 'Kancing', 'Bordir/Sablon', 'Finishing'];
            $statusToIndex = [
                'CREATED' => 0, 'QC_PREP' => 0,
                'CUTTING' => 0, 'QC_CUT_CHECK' => 0,
                'SEWING' => 1, 'QC_SEW_CHECK' => 1,
                'BUTTON' => 2,
                'DECORATION' => 3,
                'FINISHING' => 4, 'QC_FINAL_CHECK' => 4,
                'COMPLETED' => 4,
            ];
            $idx = $statusToIndex[$wo->status] ?? 0;
            DB::table('work_orders')
                ->where('id', $wo->id)
                ->update([
                    'stage_sequence' => json_encode($defaultSequence),
                    'current_stage_index' => $idx,
                    'stage_entered_at' => $wo->updated_at ?? now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'stage_sequence',
                'current_stage_index',
                'current_review_stage',
                'stage_entered_at',
                'is_express',
            ]);
        });

        // Revert status back to ENUM
        DB::statement("ALTER TABLE `work_orders` MODIFY `status` ENUM(
            'CREATED','QC_PREP','CUTTING','QC_CUT_CHECK','SEWING','QC_SEW_CHECK',
            'DECORATION','BUTTON','FINISHING','QC_FINAL_CHECK','COMPLETED'
        ) NOT NULL DEFAULT 'CREATED'");
    }
};
