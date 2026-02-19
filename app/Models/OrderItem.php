<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_name',
        'quantity',
        'price',
        'production_category',
        'size_and_request_details',
    ];

    protected $casts = [
        'size_and_request_details' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}
