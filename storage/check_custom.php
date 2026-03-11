<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$task = \App\Models\ProductionTask::whereHas('orderItem', function ($q) {
    $q->where('id', 15);
})->latest()->first();

if ($task) {
    echo json_encode([
        'task_id' => $task->id,
        'quantity' => $task->quantity,
        'size_quantities' => $task->size_quantities,
        'sizes_in_db' => gettype($task->size_quantities),
        'item_details' => $task->orderItem->size_and_request_details,
    ], JSON_PRETTY_PRINT);
} else {
    echo "No task found for order id 15.\n";
}
