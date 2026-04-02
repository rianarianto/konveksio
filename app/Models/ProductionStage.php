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
        'base_wage',
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

        static::creating(function ($model) {
            if (is_null($model->order_sequence)) {
                $maxSequence = static::where('shop_id', $model->shop_id)->max('order_sequence') ?? 0;
                $model->order_sequence = $maxSequence + 1;
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
