<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'shop_id',
        'customer_id',
        'order_number',
        'order_date',
        'deadline',
        'status',
        'is_express',
        'express_fee',
        'subtotal',
        'tax',
        'shipping_cost',
        'discount',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'order_date'  => 'date',
        'deadline'    => 'date',
        'is_express'  => 'boolean',
        'express_fee' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());

        // Auto-generate order_number: #ORD-YYYYMM-XXX
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber($order->shop_id);
            }
        });
    }

    protected static function generateOrderNumber(int $shopId): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "#ORD-{$yearMonth}-";

        // Get the last order for this shop in this month
        $lastOrder = static::withoutGlobalScope(ShopScope::class)
            ->where('shop_id', $shopId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract the sequence number from the last order
            $lastNumber = (int) str_replace($prefix, '', $lastOrder->order_number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Models\Payment::class)->orderBy('payment_date');
    }

    // ── Accessor: total yang sudah dibayar ───────────────────────────────────
    public function getTotalPaidAttribute(): int
    {
        return (int) $this->payments->sum('amount');
    }

    // ── Accessor: sisa tagihan ───────────────────────────────────────────────
    public function getRemainingBalanceAttribute(): int
    {
        return max(0, (int) $this->total_price - $this->total_paid);
    }
}
