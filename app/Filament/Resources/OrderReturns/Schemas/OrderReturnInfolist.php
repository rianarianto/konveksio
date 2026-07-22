<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use App\Models\OrderReturn;

class OrderReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order.order_number')
                    ->label('Nomor Pesanan')
                    ->weight('bold'),

                TextEntry::make('orderItem.product_name')
                    ->label('Produk Diretur')
                    ->placeholder('-'),

                TextEntry::make('return_date')
                    ->label('Tanggal Retur')
                    ->date('d M Y'),

                TextEntry::make('responsibility_type')
                    ->label('Penanggung Jawab')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'customer_paid' => '💳 Kesalahan Customer (Berbayar)',
                        default => '🛡️ Garansi Toko (Gratis Customer)',
                    })
                    ->color(fn ($state) => match ($state) {
                        'customer_paid' => 'danger',
                        default => 'success',
                    }),

                TextEntry::make('additional_fee')
                    ->label('Biaya Extra Customer')
                    ->money('IDR'),

                TextEntry::make('action_type')
                    ->label('Tindakan')
                    ->badge()
                    ->formatStateUsing(fn ($state, OrderReturn $record) => match ($state) {
                        'remake' => '🏭 Buat Baru',
                        default => '🛠️ Perbaikan (' . ucfirst($record->target_stage ?? 'Jahit') . ')',
                    })
                    ->color('info'),

                TextEntry::make('size_breakdown')
                    ->label('Rincian Ukuran / Pcs')
                    ->formatStateUsing(function ($state, OrderReturn $record) {
                        if (!empty($state) && is_array($state)) {
                            $pairs = [];
                            foreach ($state as $k => $v) {
                                $displayKey = $k === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $k;
                                $pairs[] = "{$displayKey}: {$v}";
                            }
                            return implode(', ', $pairs);
                        }
                        return $record->quantity . ' pcs';
                    }),

                TextEntry::make('quantity')
                    ->label('Total Kuantitas')
                    ->numeric(),

                TextEntry::make('status')
                    ->label('Status Retur')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Pending',
                        'diproses' => '🔨 Diproses',
                        'selesai' => '✅ Selesai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'diproses' => 'info',
                        'selesai' => 'success',
                        default => 'gray',
                    }),

                ImageEntry::make('photo_path')
                    ->label('Foto Bukti Kerusakan / Retur')
                    ->columnSpanFull(),

                TextEntry::make('items_description')
                    ->label('Catatan Keterangan Barang')
                    ->columnSpanFull(),

                TextEntry::make('reason')
                    ->label('Alasan & Rincian Retur')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->label('Dibuat Tanggal')
                    ->dateTime(),
            ]);
    }
}
