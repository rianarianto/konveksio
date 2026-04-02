<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'sort_order',
        'is_active',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
