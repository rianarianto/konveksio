<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\OrderItem::find(16);
if ($item) {
    $details = $item->size_and_request_details;
    if (!empty($details['detail_custom'])) {
        foreach ($details['detail_custom'] as $index => $u) {
            $person = trim($u['nama'] ?? 'Person ' . ($index + 1));
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $person) . '_' . $index;
            echo "Index $index: Original Name='" . ($u['nama'] ?? "") . "', Trimmed Name='$person', SafeKey='$safeKey'\n";
        }
    }
}
