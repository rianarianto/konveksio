<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductionStage;
use Illuminate\Support\Facades\DB;

class ProductionStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the default stages
        $stages = [
            [
                'name' => 'Potong',
                'base_wage' => 1000,
                'order_sequence' => 1,
                'for_produksi_custom' => true,
                'for_non_produksi' => false,
                'for_jasa' => true,
            ],
            [
                'name' => 'Jahit',
                'base_wage' => 3000,
                'order_sequence' => 2,
                'for_produksi_custom' => true,
                'for_non_produksi' => false,
                'for_jasa' => true,
            ],
            [
                'name' => 'Kancing',
                'base_wage' => 500,
                'order_sequence' => 3,
                'for_produksi_custom' => true,
                'for_non_produksi' => false,
                'for_jasa' => true,
            ],
            [
                'name' => 'Bordir/Sablon',
                'base_wage' => 2000,
                'order_sequence' => 4,
                'for_produksi_custom' => true,
                'for_non_produksi' => true,
                'for_jasa' => true,
            ],
            [
                'name' => 'Finishing',
                'base_wage' => 500,
                'order_sequence' => 5,
                'for_produksi_custom' => true,
                'for_non_produksi' => true,
                'for_jasa' => true,
            ],
            [
                'name' => 'QC',
                'base_wage' => 500,
                'order_sequence' => 6,
                'for_produksi_custom' => true,
                'for_non_produksi' => true,
                'for_jasa' => true,
            ],
        ];

        $shops = \App\Models\Shop::all();

        if ($shops->isEmpty()) {
            $this->command->warn('Tidak ada shop yang ditemukan. Buat shop terlebih dahulu (jalankan DatabaseSeeder utama).');
            return;
        }

        foreach ($shops as $shop) {
            DB::transaction(function () use ($stages, $shop) {
                // Clear existing stages for this shop to avoid mixing old and new stages
                ProductionStage::where('shop_id', $shop->id)->delete();

                foreach ($stages as $stage) {
                    // Assign to specific shop
                    ProductionStage::create(
                        array_merge($stage, ['shop_id' => $shop->id])
                    );
                }
            });
        }

        $this->command->info('Tahapan Produksi default berhasil ditambahkan untuk semua shop.');
    }
}
