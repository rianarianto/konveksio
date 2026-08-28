<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$retur = \App\Models\OrderReturn::find(1);

if ($retur) {
    if (!$retur->order_item_id) {
        $item = \App\Models\OrderItem::where('order_id', $retur->order_id)->first();
        if ($item) {
            $retur->order_item_id = $item->id;
        }
    }

    $retur->touch();
    $retur->save();

    // Reset revision tasks to pending status for retur #1
    \App\Models\ProductionTask::withoutGlobalScopes()
        ->where('order_item_id', $retur->order_item_id)
        ->where('is_revision', true)
        ->update(['status' => 'pending']);

    echo "REPAIR RETUR #1 COMPLETED! REVISION TASKS RESET TO PENDING.\n";
} else {
    echo "Retur #1 not found.\n";
}
