<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMeasurement extends Model
{
    protected $fillable = [
        'customer_id',
        'nama',
        'LD',
        'PL',
        'LP',
        'LB',
        'LPi',
        'PB',
        'catatan',
    ];

    protected $casts = [
        'LD' => 'float',
        'PL' => 'float',
        'LP' => 'float',
        'LB' => 'float',
        'LPi' => 'float',
        'PB' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
