<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'max_capacity_pcs',
        'production_spec_options',
    ];

    protected $casts = [
        'production_spec_options' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\ShopScope);

        static::created(function (Shop $shop) {
            $defaultStages = [
                ['name' => 'Potong', 'base_wage' => 1000, 'order_sequence' => 1, 'for_produksi_custom' => true, 'for_non_produksi' => false, 'for_jasa' => true],
                ['name' => 'Jahit', 'base_wage' => 3000, 'order_sequence' => 2, 'for_produksi_custom' => true, 'for_non_produksi' => false, 'for_jasa' => true],
                ['name' => 'Bordir/Sablon', 'base_wage' => 2000, 'order_sequence' => 3, 'for_produksi_custom' => true, 'for_non_produksi' => true, 'for_jasa' => true],
                ['name' => 'Kancing', 'base_wage' => 500, 'order_sequence' => 4, 'for_produksi_custom' => true, 'for_non_produksi' => false, 'for_jasa' => true],
                ['name' => 'Finishing', 'base_wage' => 500, 'order_sequence' => 5, 'for_produksi_custom' => true, 'for_non_produksi' => true, 'for_jasa' => true],
            ];

            foreach ($defaultStages as $stage) {
                \App\Models\ProductionStage::firstOrCreate(
                    ['shop_id' => $shop->id, 'name' => $stage['name']],
                    $stage
                );
            }
        });
    }
}
