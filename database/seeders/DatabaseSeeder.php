<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Owner
        User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@konveksio.test',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'shop_id' => null,
        ]);

        // Create Shop
        $shop = \App\Models\Shop::create([
            'name' => 'Konveksi Cabang Jakarta',
            'address' => 'Jl. Jendral Sudirman No. 1',
            'phone' => '081234567890',
        ]);

        // Create Admin for Shop
        User::factory()->create([
            'name' => 'Admin Jakarta',
            'email' => 'admin@jakarta.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'shop_id' => $shop->id,
        ]);

        // Create Designer for Shop 1
        User::factory()->create([
            'name' => 'Designer Jakarta',
            'email' => 'designer@jakarta.test',
            'password' => bcrypt('password'),
            'role' => 'designer',
            'shop_id' => $shop->id,
        ]);

        // Create Shop 2 (Bandung)
        $shop2 = \App\Models\Shop::create([
            'name' => 'Konveksi Cabang Bandung',
            'address' => 'Jl. Asia Afrika No. 10',
            'phone' => '081234567891',
        ]);

        // Create Admin for Shop 2
        User::factory()->create([
            'name' => 'Admin Bandung',
            'email' => 'admin@bandung.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'shop_id' => $shop2->id,
        ]);
    }
}
