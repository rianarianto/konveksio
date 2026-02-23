<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionStage extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'order_sequence',
        'for_produksi_custom',
        'for_non_produksi',
        'for_jasa',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'for_produksi_custom' => 'boolean',
        'for_non_produksi' => 'boolean',
        'for_jasa' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
