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
    
    // Trigger saved event listener to create/sync all multi-stage tasks and WO
    $retur->touch();
    $retur->save();

    echo "REPAIR RETUR #1 COMPLETED! MULTI-STAGE TASKS & WO SYNCED.\n";
} else {
    echo "Retur #1 not found.\n";
}
