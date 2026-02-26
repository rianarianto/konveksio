<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shop = \App\Models\Shop::first();
if (!$shop) {
    echo "No shop found." . PHP_EOL;
    exit;
}

$workers = [
    ['name' => 'Budi Penjahit', 'phone' => '081234567890', 'category' => 'Jahit'],
    ['name' => 'Anton Sablon', 'phone' => '081234567891', 'category' => 'Sablon'],
    ['name' => 'Siti Obras', 'phone' => '081234567892', 'category' => 'Obras'],
    ['name' => 'Joko Potong', 'phone' => '081234567893', 'category' => 'Potong'],
    ['name' => 'Wati Finishing', 'phone' => '081234567894', 'category' => 'Finishing'],
];

foreach ($workers as $w) {
    \App\Models\Worker::firstOrCreate(
        ['phone' => $w['phone']],
        [
            'shop_id' => $shop->id,
            'name' => $w['name'],
            'category' => $w['category'],
            'is_active' => true,
        ]
    );
}
echo "Seeded 5 workers successfully." . PHP_EOL;
