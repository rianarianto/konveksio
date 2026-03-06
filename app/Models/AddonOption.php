<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonOption extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'default_price',
        'is_active',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
