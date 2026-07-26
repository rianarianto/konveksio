<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\WorkOrder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class OrderDeliveryForm
{
    public static function getComponents(): array
    {
        return [
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
        // Mark all WorkOrders in this order as delivered
        $itemIds = $record->orderItems()->pluck('id');
        WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $itemIds)
            ->update(['delivered_at' => now()]);

        // Update Order status to 'selesai'
        $record->update([
            'status'       => 'selesai',
            'pickup_proof' => $data['pickup_proof'],
            'pickup_note'  => $data['pickup_note'] ?? null,
            'pickup_at'    => now(),
        ]);

        Notification::make()
            ->title('Pesanan Telah Diserahkan!')
            ->body("Pesanan #{$record->order_number} telah selesai dan seluruh barang diserahkan ke customer.")
            ->success()
            ->send();
    }

    public static function isVisible(Order $record): bool
    {
        if (!in_array(auth()->user()->role, ['owner', 'admin'])) {
            return false;
        }

        // Serahkan Pesanan only appears when the entire order is ready for pickup ('siap_diambil')
        return $record->status === 'siap_diambil';
    }
}
