<?php

use App\Models\User;
use App\Models\Shop;
use Filament\Facades\Filament;

// Login as Owner
$owner = User::where('email', 'owner@konveksio.test')->first();
auth()->login($owner);

echo "Logged in as: " . $owner->name . " (" . $owner->role . ")\n";
echo "Shop ID: " . $owner->shop_id . "\n";

// Test getTenants
$tenants = $owner->getTenants(Filament::getCurrentPanel());
echo "Tenants count: " . $tenants->count() . "\n";
foreach ($tenants as $tenant) {
    echo "- " . $tenant->name . " (ID: " . $tenant->id . ")\n";
}
