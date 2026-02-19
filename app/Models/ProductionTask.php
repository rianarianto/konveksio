<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    protected $fillable = [
        'order_item_id',
        'shop_id',
        'stage_name',
        'assigned_to',
        'assigned_by',
        'wage_amount',
        'quantity',
        'status',
        'description',
    ];

    protected $casts = [
        'wage_amount' => 'integer',
        'quantity' => 'integer',
        'status' => 'string',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    /**
     * Item order yang ditugaskan (baju apa yang dikerjakan).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Karyawan yang mengerjakan tahap ini.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Admin/Designer yang membuat penugasan ini (audit trail).
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Toko pemilik tugas ini (multi-tenancy).
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
