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
        'quantity' => 'integer',
        'price' => 'integer',
    ];

    protected $appends = [
        'bahan_baju',
        'sablon_jenis',
        'sablon_lokasi',
        'varian_ukuran',
        'request_tambahan',
        'detail_custom',
        'sablon_bordir_custom',
        'request_tambahan_custom',
        'harga_custom_satuan',
    ];

    // ─── Virtual Accessors untuk data JSON ────────────────────────────────────

    public function getBahanBajuAttribute()
    {
        return $this->size_and_request_details['bahan'] ?? null;
    }

    public function getSablonJenisAttribute()
    {
        return $this->size_and_request_details['sablon_jenis'] ?? null;
    }

    public function getSablonLokasiAttribute()
    {
        return $this->size_and_request_details['sablon_lokasi'] ?? null;
    }

    public function getVarianUkuranAttribute()
    {
        return $this->size_and_request_details['varian_ukuran'] ?? [];
    }

    public function getRequestTambahanAttribute()
    {
        return $this->size_and_request_details['request_tambahan'] ?? [];
    }

    public function getDetailCustomAttribute()
    {
        return $this->size_and_request_details['detail_custom'] ?? [];
    }

    public function getSablonBordirCustomAttribute()
    {
        return $this->size_and_request_details['sablon_bordir'] ?? [];
    }

    public function getRequestTambahanCustomAttribute()
    {
        return $this->size_and_request_details['request_tambahan'] ?? [];
    }

    public function getHargaCustomSatuanAttribute()
    {
        return $this->size_and_request_details['harga_satuan'] ?? 0;
    }

    // ──────────────────────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}
