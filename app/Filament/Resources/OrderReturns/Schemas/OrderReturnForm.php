<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Models\OrderItem;

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
                ->options(function ($get, $record) {
                    $orderId = $get('order_id');
                    if (!$orderId && $record) {
                        $orderId = $record->order_id;
                    }
                    if (!$orderId) {
                        $livewire = request()->route()?->parameter('record');
                        if ($livewire instanceof \App\Models\Order) {
                            $orderId = $livewire->id;
                        }
                    }
                    if (!$orderId) {
                        return [];
                    }
                    return OrderItem::where('order_id', $orderId)
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

            Select::make('responsibility_type')
                ->label('Penanggung Jawab / Tipe Garansi')
                ->options([
                    'store_guarantee' => '🛡️ Garansi Toko / Internal (Gratis Customer - Upah Revisi Rp 0 jika sudah gajian)',
                    'customer_paid' => '💳 Kesalahan Customer (Berbayar - Charge Tambahan & Upah Pekerja Normal)',
                ])
                ->default('store_guarantee')
                ->required()
                ->reactive(),

            TextInput::make('additional_fee')
                ->label('Biaya Tambahan Customer (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->default(0)
                ->visible(fn ($get) => $get('responsibility_type') === 'customer_paid')
                ->required(fn ($get) => $get('responsibility_type') === 'customer_paid'),

            Select::make('action_type')
                ->label('Tindakan Retur')
                ->options([
                    'repair' => '🛠️ Perbaikan (Tukang Kerjakan Ulang Divisi Tertentu)',
                    'remake' => '🏭 Buat Baru (Produksi Ulang dari Awal)',
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
                ->visible(fn ($get) => $get('action_type') === 'repair')
                ->required(fn ($get) => $get('action_type') === 'repair'),

            KeyValue::make('size_breakdown')
                ->label('Rincian Ukuran / Penerima yang Diretur')
                ->keyLabel('Ukuran / Nama Penerima')
                ->valueLabel('Jumlah (Pcs)')
                ->helperText(function ($get) {
                    $itemId = $get('order_item_id');
                    if (!$itemId) return 'Pilih barang yang diretur untuk melihat rincian ukuran tersedia.';

                    $item = OrderItem::find($itemId);
                    if (!$item) return '';

                    $groupItems = $item->getItemsInGroup();
                    $sizes = [];
                    foreach ($groupItems as $gi) {
                        $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                        $sizes[] = ($sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz) . " ({$gi->quantity} pcs)";
                    }
                    return 'Ukuran tersedia dalam pesanan ini: ' . implode(', ', $sizes);
                })
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (is_array($state)) {
                        $total = 0;
                        foreach ($state as $val) {
                            $total += is_numeric($val) ? (int)$val : 1;
                        }
                        if ($total > 0) {
                            $set('quantity', $total);
                        }
                    }
                }),

            TextInput::make('quantity')
                ->label('Total Jumlah Retur (Pcs)')
                ->required()
                ->numeric()
                ->default(1),

            Select::make('status')
                ->label('Status Retur')
                ->options([
                    'pending' => '⏳ Pending (Antrian)',
                    'diproses' => '🔨 Diproses (Sedang Dikerjakan)',
                    'selesai' => '✅ Selesai (Selesai Diretur)',
                ])
                ->required()
                ->default('pending'),

            FileUpload::make('photo_path')
                ->label('Foto Bukti Kerusakan / Retur')
                ->image()
                ->directory('order-returns')
                ->maxSize(5120)
                ->columnSpanFull(),

            Textarea::make('items_description')
                ->label('Catatan Keterangan Barang')
                ->placeholder('Misal: Kaos M warna merah jahitan lepas, sablon miring')
                ->required()
                ->columnSpanFull(),

            Textarea::make('reason')
                ->label('Alasan & Rincian Retur')
                ->placeholder('Tuliskan alasan retur atau instruksi perbaikan untuk tukang...')
                ->columnSpanFull(),
        ]);
    }
}
