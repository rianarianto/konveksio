<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

class OrderItemObserver
{
    /**
     * Sebelum OrderItem dibuat, jika produk yang sama dalam order ini sudah disetujui desainnya,
     * otomatis set design_status ke approved dan salin design_image agar tidak masuk ke meja desain lagi.
     */
    public function creating(OrderItem $item): void
    {
        // Auto-mark as additional item if the order has already started production
        if ($item->order && $item->order->hasStartedProduction()) {
            $item->is_addition = true;
        }

        $existingApproved = OrderItem::where('order_id', $item->order_id)
            ->where('product_name', $item->product_name)
            ->where('design_status', 'approved')
            ->first();

        if ($existingApproved && !$item->is_addition) {
            $item->design_status = 'approved';
            $item->design_image = $existingApproved->design_image;
        }
    }

    /**
     * Auto-create WorkOrder saat OrderItem baru dibuat.
     * Semua kategori (produksi, custom, jasa, non_produksi) mendapat WO
     * dengan jalur state machine yang berbeda sesuai kategorinya.
     */
    public function created(OrderItem $item): void
    {
        // Jika status pesanan masih draft, ubah otomatis ke 'diterima' agar langsung aktif
        $order = $item->order;
        if ($order && $order->status === 'draft') {
            $order->update(['status' => 'diterima']);
        }

        // Cegah duplikasi jika sudah ada WO di item ini atau di item lain dalam grup yang sama
        if ($item->workOrder()->exists()) {
            return;
        }

        $groupItemIds = $item->getItemsInGroup()->pluck('id');
        $existingWo = WorkOrder::whereIn('order_item_id', $groupItemIds)->exists();
        if ($existingWo) {
            return;
        }

        try {
            $shopId = $item->order?->shop_id;

            if (!$shopId) {
                Log::warning("[WorkOrder] Tidak bisa buat WO: shop_id kosong untuk OrderItem #{$item->id}");
                return;
            }

            // Tentukan decoration_type dari data JSON order item
            $decorationType = null;
            $sablonJenis    = $item->size_and_request_details['sablon_jenis'] ?? null;
            if ($sablonJenis) {
                $decorationType = match($sablonJenis) {
                    'sablon'      => 'sablon_manual',
                    'sablon_dtf'  => 'sablon_dtf',
                    'bordir'      => 'bordir_reguler',
                    'bordir_3d'   => 'bordir_3d',
                    default       => null,
                };
            }

            WorkOrder::create([
                'shop_id'         => $shopId,
                'order_item_id'   => $item->id,
                'decoration_type' => $decorationType,
                // token & wo_number di-generate otomatis di model boot()
            ]);

            Log::info("[WorkOrder] WO berhasil dibuat untuk OrderItem #{$item->id}");
        } catch (\Exception $e) {
            Log::error("[WorkOrder] Gagal buat WO untuk OrderItem #{$item->id}: " . $e->getMessage());
        }
    }
}
