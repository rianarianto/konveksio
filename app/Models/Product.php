<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'category',
        'type',
        'color_code',
        'supplier_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
