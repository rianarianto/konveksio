<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'category',
        'type',
        'unit',
        'color_code',
        'current_stock',
        'min_stock',
        'supplier_id',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventoryLogs()
    {
        return $this->morphMany(InventoryLog::class, 'stockable');
    }
}
