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
        // --- LOGIKA PENGAMAN: JANGAN KEMBALIKAN STOK JIKA PESANAN SUDAH SELESAI/BATAL ---
        // Jika kita sedang menghapus data (newDetails null) dan status pesanan sudah Selesai/Batal, 
        // maka jangan kembalikan stok ke katalog.
        $status = strtolower($this->order?->status ?? '');
        if ($newDetails === null && in_array($status, ['selesai', 'batal'])) {
            return;
        }

        DB::transaction(function () use ($oldDetails, $newDetails) {
            // --- A. PENANGANAN BAHAN BAKU (PRODUKSI/CUSTOM) ---
            // Support both old keys ('bahan', 'bahan_usage') and new keys ('material_variant_id', 'stock_qty_used')
            $oldBahanId = $oldDetails['material_variant_id'] ?? $oldDetails['bahan'] ?? $this->getOriginal('bahan_id');
            $oldBahanUsage = (float) ($oldDetails['stock_qty_used'] ?? $oldDetails['bahan_usage'] ?? 0);
            
            $newBahanId = $newDetails['material_variant_id'] ?? $newDetails['bahan'] ?? $this->bahan_id;
            $newBahanUsage = (float) ($newDetails['stock_qty_used'] ?? $newDetails['bahan_usage'] ?? 0);

            // Jika ada perubahan bahan atau jumlah pemakaian
            if ($oldBahanId != $newBahanId || $oldBahanUsage != $newBahanUsage) {
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
            // 1. Handle New Format (One Variant per Row via product_variant_id)
            $oldVariantId = $oldDetails['product_variant_id'] ?? null;
            $oldProductUsage = (int) ($oldDetails['stock_qty_used'] ?? (isset($oldDetails['use_stock']) && $oldDetails['use_stock'] ? 1 : 0));
            if (!isset($oldDetails['stock_qty_used']) && !isset($oldDetails['use_stock'])) {
                $oldProductUsage = $this->getOriginal('quantity') ?: 1; // Fallback for very old data
            }

            $newVariantId = $newDetails['product_variant_id'] ?? null;
            $newProductUsage = (int) ($newDetails['stock_qty_used'] ?? (isset($newDetails['use_stock']) && $newDetails['use_stock'] ? 1 : 0));
            if (!isset($newDetails['stock_qty_used']) && !isset($newDetails['use_stock'])) {
                $newProductUsage = $this->quantity ?: 1; // Fallback for very old data
            }

            if ($oldVariantId != $newVariantId || $oldProductUsage != $newProductUsage) {
                if ($oldVariantId && $oldProductUsage > 0) {
                    ProductVariant::where('id', $oldVariantId)->increment('stock', $oldProductUsage);
                }
                if ($newVariantId && $newProductUsage > 0) {
                    ProductVariant::where('id', $newVariantId)->decrement('stock', $newProductUsage);
                }
            }

            // 2. Handle Old/Legacy Format (Bulk Variants in 'varian_ukuran')
            $oldProduct = $oldDetails['supplier_product'] ?? null;
            $oldVariants = $oldDetails['varian_ukuran'] ?? [];
            $newProduct = $newDetails['supplier_product'] ?? null;
            $newVariants = $newDetails['varian_ukuran'] ?? [];

            if ($oldProduct && count($oldVariants) > 0) {
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

            if ($newProduct && count($newVariants) > 0) {
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
        if (!in_array($this->production_category, ['produksi', 'custom'])) return null;
        return $this->size_and_request_details['gender'] ?? 'L';
    }

    public function getSleeveModelAttribute()
    {
        if (!in_array($this->production_category, ['produksi', 'custom'])) return null;
        return $this->size_and_request_details['sleeve_model'] ?? 'pendek';
    }

    public function getPocketModelAttribute()
    {
        if (!in_array($this->production_category, ['produksi', 'custom'])) return null;
        return $this->size_and_request_details['pocket_model'] ?? 'tanpa_saku';
    }

    public function getButtonModelAttribute()
    {
        if (!in_array($this->production_category, ['produksi', 'custom'])) return null;
        return $this->size_and_request_details['button_model'] ?? 'biasa';
    }

    public function getIsTunicAttribute()
    {
        return (bool) ($this->size_and_request_details['is_tunic'] ?? false);
    }

    // --- Virtual Setters ---
    public function setGenderAttribute($value)
    {
        $details = $this->size_and_request_details ?? [];
        $details['gender'] = $value;
        $this->size_and_request_details = $details;
    }

    public function setSleeveModelAttribute($value)
    {
        $details = $this->size_and_request_details ?? [];
        $details['sleeve_model'] = $value;
        $this->size_and_request_details = $details;
    }

    public function setPocketModelAttribute($value)
    {
        $details = $this->size_and_request_details ?? [];
        $details['pocket_model'] = $value;
        $this->size_and_request_details = $details;
    }

    public function setButtonModelAttribute($value)
    {
        $details = $this->size_and_request_details ?? [];
        $details['button_model'] = $value;
        $this->size_and_request_details = $details;
    }

    public function setIsTunicAttribute($value)
    {
        $details = $this->size_and_request_details ?? [];
        $details['is_tunic'] = (bool) $value;
        $this->size_and_request_details = $details;
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

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'size_and_request_details->product_variant_id');
    }

    public function orderShop()
    {
        return $this->hasOneThrough(Shop::class, Order::class, 'shop_id', 'id', 'order_id', 'shop_id');
    }
}
