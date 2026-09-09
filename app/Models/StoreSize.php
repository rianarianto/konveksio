<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'size_details',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
