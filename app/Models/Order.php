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
        'created_by',
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
        'pickup_proof',
        'pickup_note',
        'pickup_at',
    ];

    protected $casts = [
        'order_date'  => 'date',
        'deadline'    => 'date',
        'is_express'  => 'boolean',
        'express_fee' => 'integer',
        'subtotal'    => 'integer',
        'tax'         => 'integer',
        'shipping_cost' => 'integer',
        'discount'    => 'integer',
        'total_price' => 'integer',
        'pickup_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());

        // Auto-generate order_number: #ORD-YYYYMM-XXX
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber($order->shop_id);
            }
            if (empty($order->status)) {
                $order->status = 'draft';
            }
            if (empty($order->created_by) && auth()->check()) {
                $order->created_by = auth()->id();
            }
        });

        // Ensure financial fields are never null and recalculate total_price
        static::saving(function ($order) {
            $order->tax = $order->tax ?? 0;
            $order->shipping_cost = $order->shipping_cost ?? 0;
            $order->discount = $order->discount ?? 0;
            $order->express_fee = $order->express_fee ?? 0;
            $order->subtotal = $order->subtotal ?? 0;
            
            // Recalculate total price to ensure consistency
            $actualExpressFee = $order->is_express ? $order->express_fee : 0;
            $order->total_price = max(0, $order->subtotal + $order->tax + $order->shipping_cost + $actualExpressFee - $order->discount);
        });

        // Handle cancellation
        static::updated(function ($order) {
            if ($order->isDirty('status') && $order->status === 'batal') {
                $orderItemIds = $order->orderItems()->pluck('id');
                
                // Delete pending and in_progress tasks
                \App\Models\ProductionTask::whereIn('order_item_id', $orderItemIds)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->delete();
                    
                // Set wage_amount to 0 for done but unpaid tasks
                \App\Models\ProductionTask::whereIn('order_item_id', $orderItemIds)
                    ->where('status', 'done')
                    ->where('is_paid', false)
                    ->update([
                        'wage_amount' => 0,
                    ]);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Check if this order has started production in any of its WorkOrders.
     */
    public function hasStartedProduction(): bool
    {
        $itemIds = $this->orderItems()->pluck('id');
        $wos = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $itemIds)
            ->get();
        
        if ($wos->isEmpty()) {
            return false;
        }

        foreach ($wos as $wo) {
            if (!in_array($wo->status, [WorkOrder::STATUS_CREATED, 'NO_WO'])) {
                return true;
            }
        }

        return false;
    }
}
