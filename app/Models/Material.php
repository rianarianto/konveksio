<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'category',
        'type',
        'unit',
        'supplier_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function variants()
    {
        return $this->hasMany(MaterialVariant::class);
    }

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
