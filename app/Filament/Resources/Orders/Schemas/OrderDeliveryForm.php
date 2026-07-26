<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\WorkOrder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class OrderDeliveryForm
{
    public static function getComponents(): array
    {
        return [
            CheckboxList::make('delivered_wo_ids')
                ->label('Pilih Barang yang Diserahkan Saat Ini')
                ->options(function ($record) {
                    if (!$record) return [];
                    $order = $record instanceof Order ? $record : $record->order ?? null;
                    if (!$order) return [];

                    $itemIds = $order->orderItems()->pluck('id');
                    $wos = WorkOrder::withoutGlobalScopes()
                        ->whereIn('order_item_id', $itemIds)
                        ->with('orderItem')
                        ->get();
                    
                    $options = [];
                    foreach ($wos as $wo) {
                        $itemName = $wo->orderItem?->product_name ?? 'Barang';
                        $statusLabel = $wo->isCompleted() ? '✅ Selesai Produksi' : '🔨 Masih Diproses (' . $wo->status_label . ')';
                        if ($wo->isDelivered()) {
                            $statusLabel = '📦 Sudah Diserahkan (' . $wo->delivered_at->format('d M Y H:i') . ')';
                        }
                        $options[$wo->id] = "{$itemName} — {$statusLabel}";
                    }
                    return $options;
                })
                ->descriptions(function ($record) {
                    if (!$record) return [];
                    $order = $record instanceof Order ? $record : $record->order ?? null;
                    if (!$order) return [];

                    $itemIds = $order->orderItems()->pluck('id');
                    $wos = WorkOrder::withoutGlobalScopes()
                        ->whereIn('order_item_id', $itemIds)
                        ->with('orderItem')
                        ->get();

                    $descriptions = [];
                    foreach ($wos as $wo) {
                        $qty = $wo->orderItem?->quantity ?? 0;
                        $size = $wo->orderItem?->size ? " (" . ($wo->orderItem->size === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $wo->orderItem->size) . ")" : "";
                        $descriptions[$wo->id] = "Jumlah: {$qty} pcs{$size}";
                    }
                    return $descriptions;
                })
                ->default(function ($record) {
                    if (!$record) return [];
                    $order = $record instanceof Order ? $record : $record->order ?? null;
                    if (!$order) return [];

                    $itemIds = $order->orderItems()->pluck('id');
                    return WorkOrder::withoutGlobalScopes()
                        ->whereIn('order_item_id', $itemIds)
                        ->where('status', WorkOrder::STATUS_COMPLETED)
                        ->whereNull('delivered_at')
                        ->pluck('id')
                        ->toArray();
                })
                ->required()
                ->columns(1),

            FileUpload::make('pickup_proof')
                ->label('Foto Bukti Pengambilan / Penyerahan')
                ->image()
                ->extraInputAttributes(['capture' => 'camera'])
                ->disk('public')
                ->directory('pickup-proofs')
                ->required(),

            Textarea::make('pickup_note')
                ->label('Catatan Penyerahan')
                ->rows(3)
                ->placeholder('Masukkan nama pengambil, kurir, atau info penyerahan lainnya jika ada...'),
        ];
    }

    public static function processDelivery(Order $record, array $data): void
    {
        $selectedWoIds = $data['delivered_wo_ids'] ?? [];
        if (!empty($selectedWoIds)) {
            WorkOrder::withoutGlobalScopes()
                ->whereIn('id', $selectedWoIds)
                ->update(['delivered_at' => now()]);
        }

        $record->update([
            'pickup_proof' => $data['pickup_proof'],
            'pickup_note'  => $data['pickup_note'] ?? null,
            'pickup_at'    => now(),
        ]);

        // Check if ALL WorkOrders in this Order have been completed and delivered
        $allItemIds = $record->orderItems()->pluck('id');
        $allWos = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $allItemIds)
            ->get();

        $allDelivered = $allWos->every(fn($w) => $w->isCompleted() && $w->isDelivered());
        
        if ($allDelivered) {
            $record->update(['status' => 'selesai']);
            Notification::make()
                ->title('Seluruh Pesanan Telah Diserahkan!')
                ->body("Pesanan #{$record->order_number} telah selesai 100% dan seluruh barang telah diserahkan.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Sebagian Barang Telah Diserahkan!')
                ->body("Barang terpilih pada pesanan #{$record->order_number} berhasil dicatat penyerahannya.")
                ->success()
                ->send();
        }
    }

    public static function isVisible(Order $record): bool
    {
        if (!in_array(auth()->user()->role, ['owner', 'admin'])) {
            return false;
        }

        // Visible if there is at least one completed WorkOrder that has NOT been delivered yet
        $itemIds = $record->orderItems()->pluck('id');
        return WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $itemIds)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->whereNull('delivered_at')
            ->exists();
    }
}
