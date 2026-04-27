<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\Material;
use App\Models\MaterialVariant;
use App\Models\ProductVariant;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_name',
        'quantity',
        'price',
        'production_category',
        'size',
        'bahan_id',
        'recipient_name',
        'size_and_request_details',
        'design_status',
        'design_image',
    ];

    protected $casts = [
        'size_and_request_details' => 'array',
        'quantity' => 'integer',
        'price' => 'integer',
        'design_status' => 'string',
    ];

    protected static function booted(): void
    {
        // 1. Potong Stok Saat Pesanan Dibuat
        static::created(function (OrderItem $item) {
            $item->adjustStock(null, $item->size_and_request_details);
        });

        // 2. Sinkronkan Stok Saat Pesanan Diubah (Edit)
        static::updated(function (OrderItem $item) {
            // Kita bandingkan data lama dan baru
            $oldDetails = $item->getOriginal('size_and_request_details');
            $newDetails = $item->size_and_request_details;

            $item->adjustStock($oldDetails, $newDetails);
        });

        // 3. Kembalikan Stok Saat Pesanan Dihapus (Refund Sebelum Selesai)
        static::deleted(function (OrderItem $item) {
            $item->adjustStock($item->size_and_request_details, null);
        });
    }

    /**
     * Logika Utama Penyesuaian Stok (Bahan & Baju)
     */
    protected function adjustStock(?array $oldDetails, ?array $newDetails): void
    {
        DB::transaction(function () use ($oldDetails, $newDetails) {
            // --- A. PENANGANAN BAHAN BAKU (PRODUKSI/CUSTOM) ---
            $oldBahanId = $oldDetails['bahan'] ?? null;
            $oldBahanUsage = (float) ($oldDetails['bahan_usage'] ?? 0);
            
            $newBahanId = $newDetails['bahan'] ?? null;
            $newBahanUsage = (float) ($newDetails['bahan_usage'] ?? 0);

            // Jika ada perubahan bahan atau jumlah pemakaian
            if ($oldBahanId !== $newBahanId || $oldBahanUsage !== $newBahanUsage) {
                // Refund data lama jika ada
                if ($oldBahanId && $oldBahanUsage > 0) {
                    MaterialVariant::where('id', $oldBahanId)->increment('current_stock', $oldBahanUsage);
                }
                // Potong data baru jika ada
                if ($newBahanId && $newBahanUsage > 0) {
                    MaterialVariant::where('id', $newBahanId)->decrement('current_stock', $newBahanUsage);
                }
            }

            // --- B. PENANGANAN BAJU JADI (NON-PRODUKSI) ---
            $oldProduct = $oldDetails['supplier_product'] ?? null;
            $oldVariants = $oldDetails['varian_ukuran'] ?? [];
            
            $newProduct = $newDetails['supplier_product'] ?? null;
            $newVariants = $newDetails['varian_ukuran'] ?? [];

            // 1. Refund semua stok varian lama
            if ($oldProduct) {
                foreach ($oldVariants as $ov) {
                    $sz = $ov['ukuran'] ?? null;
                    $usage = (int) ($ov['stok_digunakan'] ?? 0);
                    if ($sz && $usage > 0) {
                        ProductVariant::where('product_id', $oldProduct)
                            ->whereRaw('LOWER(size) = ?', [strtolower($sz)])
                            ->increment('stock', $usage);
                    }
                }
            }

            // 2. Potong stok varian baru
            if ($newProduct) {
                foreach ($newVariants as $nv) {
                    $sz = $nv['ukuran'] ?? null;
                    $usage = (int) ($nv['stok_digunakan'] ?? 0);
                    if ($sz && $usage > 0) {
                        ProductVariant::where('product_id', $newProduct)
                            ->whereRaw('LOWER(size) = ?', [strtolower($sz)])
                            ->decrement('stock', $usage);
                    }
                }
            }
        });
    }

    protected $appends = [
        'bahan_baju',
        'gender',
        'sleeve_model',
        'pocket_model',
        'button_model',
        'is_tunic',
        'tunic_fee',
        'measurements',
        'sablon_jenis',
        'sablon_lokasi',
        'varian_ukuran',
        'request_tambahan',
        'detail_custom',
        'sablon_bordir_custom',
        'request_tambahan_custom',
        'harga_custom_satuan',
        'bahan_usage',
    ];

    // ─── Virtual Accessors untuk data JSON ────────────────────────────────────

    public function getBahanBajuAttribute()
    {
        return $this->bahan_id ?: ($this->size_and_request_details['bahan'] ?? null);
    }

    public function getGenderAttribute()
    {
        return $this->size_and_request_details['gender'] ?? 'L';
    }

    public function getSleeveModelAttribute()
    {
        return $this->size_and_request_details['sleeve_model'] ?? 'pendek';
    }

    public function getPocketModelAttribute()
    {
        return $this->size_and_request_details['pocket_model'] ?? 'tanpa_saku';
    }

    public function getButtonModelAttribute()
    {
        return $this->size_and_request_details['button_model'] ?? 'biasa';
    }

    public function getIsTunicAttribute()
    {
        return (bool) ($this->size_and_request_details['is_tunic'] ?? false);
    }

    public function getTunicFeeAttribute()
    {
        return (int) ($this->size_and_request_details['tunic_fee'] ?? 0);
    }

    public function getMeasurementsAttribute()
    {
        return $this->size_and_request_details['measurements'] ?? [
            'LD' => null, 'PB' => null, 'PL' => null, 'LB' => null, 'LP' => null, 'LPh' => null
        ];
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

    public function getBahanUsageAttribute()
    {
        return $this->size_and_request_details['bahan_usage'] ?? null;
    }

    // ──────────────────────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(MaterialVariant::class, 'bahan_id');
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }

    public function orderShop()
    {
        return $this->hasOneThrough(Shop::class, Order::class, 'shop_id', 'id', 'order_id', 'shop_id');
    }
}
