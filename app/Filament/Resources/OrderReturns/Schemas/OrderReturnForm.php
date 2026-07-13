<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Schemas\Schema;

class OrderReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::getComponents());
    }

    public static function getComponents(bool $skipContextFields = false): array
    {
        $components = [];

        if (!$skipContextFields) {
            $components[] = Select::make('shop_id')
                ->label('Toko')
                ->relationship('shop', 'name')
                ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                ->required();

            $components[] = Select::make('order_id')
                ->label('Pesanan')
                ->relationship('order', 'order_number')
                ->searchable()
                ->preload()
                ->required()
                ->reactive();
        }

        return array_merge($components, [
            Select::make('order_item_id')
                ->label('Barang yang Diretur')
                ->options(function (Get $get, $record) {
                    $orderId = $get('order_id');
                    if (!$orderId && $record) {
                        $orderId = $record->order_id;
                    }
                    if (!$orderId) {
                        // fallback to current order context if available
                        $livewire = request()->route()?->parameter('record');
                        if ($livewire instanceof \App\Models\Order) {
                            $orderId = $livewire->id;
                        }
                    }
                    if (!$orderId) {
                        return [];
                    }
                    return \App\Models\OrderItem::where('order_id', $orderId)
                        ->get()
                        ->mapWithKeys(fn($item) => [$item->id => $item->product_name . ' (' . ($item->size ?? 'All Size') . ')'])
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->reactive(),
            DatePicker::make('return_date')
                ->label('Tanggal Retur')
                ->default(now())
                ->required(),
            TextInput::make('quantity')
                ->label('Jumlah (Pcs)')
                ->required()
                ->numeric()
                ->default(1),
            Select::make('action_type')
                ->label('Tindakan Retur')
                ->options([
                    'repair' => 'Perbaikan (Tukang Kerjakan Ulang)',
                    'remake' => 'Buat Baru (Produksi Ulang dari Awal)',
                ])
                ->required()
                ->reactive()
                ->default('repair'),
            Select::make('target_stage')
                ->label('Kirim Kembali Ke Tahap (Untuk Perbaikan)')
                ->options([
                    'potong' => 'Potong',
                    'sablon' => 'Sablon/Bordir',
                    'jahit' => 'Jahit',
                    'qc' => 'QC',
                ])
                ->visible(fn (Get $get) => $get('action_type') === 'repair')
                ->required(fn (Get $get) => $get('action_type') === 'repair'),
            Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Pending',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                ])
                ->required()
                ->default('pending'),
            Textarea::make('items_description')
                ->label('Catatan Keterangan Barang')
                ->placeholder('Misal: Kaos XL warna merah luntur')
                ->required()
                ->columnSpanFull(),
            Textarea::make('reason')
                ->label('Alasan Retur')
                ->placeholder('Misal: Jahitan di ketiak lepas, sablon miring, dll.')
                ->columnSpanFull(),
        ]);
    }
}
