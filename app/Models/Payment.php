<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'amount',
        'payment_date',
        'payment_method',
        'note',
        'proof_image',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'integer',
    ];

    // ── Relasi ───────────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Label method untuk enum method ───────────────────────────────────────

    public function methodLabel(): string
    {
        return match($this->payment_method) {
            'transfer' => 'Transfer Bank',
            'qris'     => 'QRIS',
            default    => 'Cash',
        };
    }
}
