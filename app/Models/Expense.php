<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'shop_id',
        'keperluan',
        'amount',
        'expense_date',
        'proof_image',
        'recorded_by',
        'note',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
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
