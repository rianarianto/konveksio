<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCorrectionRequest extends Model
{
    protected $fillable = [
        'shop_id',
        'payment_id',
        'request_type',
        'old_amount',
        'old_payment_method',
        'old_payment_date',
        'old_note',
        'old_proof_image',
        'new_amount',
        'new_payment_method',
        'new_payment_date',
        'new_note',
        'new_proof_image',
        'reason',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'old_amount'       => 'integer',
        'new_amount'       => 'integer',
        'old_payment_date' => 'date',
        'new_payment_date' => 'date',
        'reviewed_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
