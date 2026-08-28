<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$retur = \App\Models\OrderReturn::find(1);

if ($retur) {
    // 1. Set Item ID 1 (Kaos Olahraga SMKN 1 Padang) & target stage
    $retur->order_item_id = 1;
    if (empty($retur->target_stage)) {
        $retur->target_stage = 'Bordir/Sablon';
    }
    $retur->status = 'pending';
    $retur->save();

    // 2. Create Retur Work Order (#WO-202608-001-R1) if missing
    $wo = \App\Models\WorkOrder::withoutGlobalScopes()
        ->where('order_item_id', 1)
        ->where('wo_number', 'like', '%-R%')
        ->first();

    if (!$wo) {
        $targetStages = array_filter(array_map('trim', explode(',', (string)$retur->target_stage)));
        if (empty($targetStages)) {
            $targetStages = ['Bordir/Sablon'];
        }

        \App\Models\WorkOrder::create([
            'shop_id' => $retur->shop_id ?: 1,
            'order_item_id' => 1,
            'wo_number' => '#WO-202608-001-R1',
            'status' => $targetStages[0],
            'stage_sequence' => $targetStages,
            'current_stage_index' => 0,
            'stage_entered_at' => now(),
            'token' => \Illuminate\Support\Str::random(64),
            'is_express' => true,
            'has_qc_prep' => false,
            'has_qc_selesai' => true,
        ]);
    }

    // 3. Create Retur Production Task if missing
    $task = \App\Models\ProductionTask::withoutGlobalScopes()
        ->where('order_item_id', 1)
        ->where('is_revision', true)
        ->first();

    if (!$task) {
        $targetStages = array_filter(array_map('trim', explode(',', (string)$retur->target_stage)));
        if (empty($targetStages)) {
            $targetStages = ['Bordir/Sablon'];
        }

        foreach ($targetStages as $stg) {
            \App\Models\ProductionTask::create([
                'shop_id' => $retur->shop_id ?: 1,
                'order_item_id' => 1,
                'stage_name' => $stg,
                'quantity' => $retur->quantity ?: 1,
                'wage_amount' => 0,
                'status' => 'pending',
                'is_revision' => true,
                'revision_source' => 'qc_akhir',
                'assigned_by' => 1,
                'assigned_to' => null,
                'size_quantities' => $retur->size_breakdown,
                'note' => '⚠️ REVISI RETUR: ' . ($retur->reason ?: 'Bordir namanya salah'),
            ]);
        }
    }

    echo "SUCCESSFULLY REPAIRED RETUR ID 1 & CREATED WO #WO-202608-001-R1!\n";
} else {
    echo "Retur ID 1 not found.\n";
}
