<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintType extends Model
{
    protected $fillable = ['shop_id', 'category', 'name', 'is_active'];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ShopScope());
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
