<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;

class AddonOption extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'default_price',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
