<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shop = App\Models\Shop::first();
echo "Shop ID: " . ($shop->id ?? 'none') . PHP_EOL;
echo "Shop Name: " . ($shop->name ?? 'none') . PHP_EOL;
echo "Existing users: " . App\Models\User::count() . PHP_EOL;
$roles = App\Models\User::select('role')->distinct()->pluck('role');
echo "Roles: " . $roles->implode(', ') . PHP_EOL;
