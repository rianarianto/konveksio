<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\OrderItem::find(15);
if ($item) {
    echo json_encode([
        'id' => $item->id,
        'category' => $item->production_category,
        'details' => $item->size_and_request_details,
    ], JSON_PRETTY_PRINT);
} else {
    echo "OrderItem 15 not found.";
}
