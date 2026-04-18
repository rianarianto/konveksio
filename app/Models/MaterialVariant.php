<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialVariant extends Model
{
    protected $fillable = [
        'material_id',
        'color_name',
        'color_code',
        'current_stock',
        'min_stock',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function inventoryLogs()
    {
        return $this->morphMany(InventoryLog::class, 'stockable');
    }
}
