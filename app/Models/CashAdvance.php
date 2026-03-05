<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashAdvance extends Model
{
    protected $fillable = [
        'shop_id',
        'cash_advanceable_type',
        'cash_advanceable_id',
        'type',
        'amount',
        'date',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    /**
     * Polymorphic: bisa User atau Worker.
     */
    public function cashAdvanceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
