<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$retur = \App\Models\OrderReturn::find(1);

if ($retur && $retur->order_item_id) {
    $item = $retur->orderItem;
    if ($item) {
        $wo = \App\Models\WorkOrder::withoutGlobalScopes()
            ->where('order_item_id', $item->id)
            ->where('wo_number', 'like', '%-R%')
            ->first();

        if (!$wo) {
            $rawStages = array_filter(array_map('trim', explode(',', (string) $retur->target_stage)));
            if (empty($rawStages)) {
                $rawStages = ['Bordir/Sablon'];
            }

            foreach ($rawStages as $stg) {
                \App\Models\ProductionTask::create([
                    'shop_id' => $retur->shop_id,
                    'order_item_id' => $item->id,
                    'stage_name' => $stg,
                    'quantity' => $retur->quantity,
                    'wage_amount' => 0,
                    'status' => 'pending',
                    'is_revision' => true,
                    'revision_source' => 'qc_akhir',
                    'assigned_by' => 1,
                    'assigned_to' => null,
                    'size_quantities' => $retur->size_breakdown,
                    'note' => '⚠️ REVISI RETUR: ' . $retur->reason,
                ]);
            }

            \App\Models\WorkOrder::create([
                'shop_id' => $retur->shop_id,
                'order_item_id' => $item->id,
                'wo_number' => 'WO-' . str_pad((string) $item->id, 5, '0', STR_PAD_LEFT) . '-R1',
                'status' => $rawStages[0],
                'stage_sequence' => $rawStages,
                'current_stage_index' => 0,
                'stage_entered_at' => now(),
                'token' => \Illuminate\Support\Str::random(64),
                'is_express' => false,
                'has_qc_prep' => false,
                'has_qc_selesai' => true,
            ]);

            echo "REPAIR WORK ORDER RETUR #1 SUCCESSFUL!\n";
        } else {
            echo "Work Order Retur #1 already exists.\n";
        }
    }
} else {
    echo "Retur #1 not found or order_item_id missing.\n";
}
