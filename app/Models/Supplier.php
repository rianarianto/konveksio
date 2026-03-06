<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'type',
        'phone',
        'address',
        'bank_info',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
