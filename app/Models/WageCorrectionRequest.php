<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WageCorrectionRequest extends Model
{
    protected $fillable = [
        'shop_id',
        'production_task_id',
        'old_wage_per_pcs',
        'old_wage_custom_per_pcs',
        'old_wage_amount',
        'new_wage_per_pcs',
        'new_wage_custom_per_pcs',
        'new_wage_amount',
        'reason',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'old_wage_per_pcs'        => 'integer',
        'old_wage_custom_per_pcs' => 'integer',
        'old_wage_amount'         => 'integer',
        'new_wage_per_pcs'        => 'integer',
        'new_wage_custom_per_pcs' => 'integer',
        'new_wage_amount'         => 'integer',
        'reviewed_at'             => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function productionTask(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

