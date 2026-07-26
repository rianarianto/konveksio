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
                ->relationship('order', 'order_number', modifyQueryUsing: fn ($query) => $query->where('status', 'selesai'))
                ->searchable()
                ->preload()
                ->required()
                ->reactive();
        }

        return array_merge($components, [
            // Step 1: Pilih Produk Utama
            Select::make('selected_product_name')
                ->label('1. Pilih Produk / Item Pesanan Utama')
                ->options(function ($get, $record) {
                    $orderId = $get('order_id');
                    if (!$orderId && $record) {
                        if ($record instanceof \App\Models\Order) {
                            $orderId = $record->id;
                        } elseif (isset($record->order_id)) {
                            $orderId = $record->order_id;
                        }
                    }
                    if (!$orderId) {
                        $routeRecord = request()->route()?->parameter('record');
                        if ($routeRecord instanceof \App\Models\Order) {
                            $orderId = $routeRecord->id;
                        } elseif (is_numeric($routeRecord)) {
                            $orderId = (int) $routeRecord;
                        }
                    }
                    if (!$orderId) return [];

                    $items = OrderItem::where('order_id', $orderId)->get();
                    $grouped = $items->groupBy(fn($i) => $i->product_name ?: 'Item');

                    $options = [];
                    foreach ($grouped as $pName => $group) {
                        $totQty = $group->sum('quantity');
                        $cat = match($group->first()->production_category) {
                            'custom' => '🧵 Produksi (Custom)',
                            'non_produksi' => '📦 Non-Produksi',
                            'jasa' => '🔧 Jasa',
                            default => '🏭 Produksi',
                        };
                        $isAddition = $group->first()->is_addition ? ' ➕ [Item Tambahan]' : '';
                        $options[$pName] = "{$cat} - {$pName}{$isAddition} (Total: {$totQty} pcs)";
                    }
                    return $options;
                })
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('order_item_id', null)),

            // Step 2: Pilih Ukuran / Varian Spesifik (Laki-laki / Perempuan / Custom)
            Select::make('order_item_id')
                ->label('2. Pilih Ukuran & Varian Spesifik yang Diretur')
                ->options(function ($get, $record) {
                    $pName = $get('selected_product_name');
                    $orderId = $get('order_id');
                    if (!$orderId && $record) {
                        if ($record instanceof \App\Models\Order) {
                            $orderId = $record->id;
                        } elseif (isset($record->order_id)) {
                            $orderId = $record->order_id;
                        }
                    }
                    if (!$orderId) {
                        $routeRecord = request()->route()?->parameter('record');
                        if ($routeRecord instanceof \App\Models\Order) {
                            $orderId = $routeRecord->id;
                        } elseif (is_numeric($routeRecord)) {
                            $orderId = (int) $routeRecord;
                        }
                    }
                    if (!$orderId || !$pName) return [];

                    $items = OrderItem::where('order_id', $orderId)
                        ->where('product_name', $pName)
                        ->get();

                    $options = [];
                    foreach ($items as $item) {
                        $size = $item->size ? "Ukuran: {$item->size}" : "Tanpa Ukuran";
                        $qty = " ({$item->quantity} pcs)";
                        
                        $details = [];
                        if ($item->recipient_name) {
                            $details[] = "Penerima: {$item->recipient_name}";
                        }

                        $reqDetails = $item->size_and_request_details ?? [];
                        if (!empty($reqDetails['gender'])) {
                            $genderLabel = $reqDetails['gender'] === 'L' ? 'Laki-laki' : 'Perempuan';
                            $details[] = "Kategori: {$genderLabel}";
                        }

                        // Specific clothing model specs (lengan, kerah, etc.)
                        $specs = [];
                        if (!empty($reqDetails['sleeve_model'])) {
                            $sleeve = str_replace('_', ' ', (string)$reqDetails['sleeve_model']);
                            $specs[] = "Lengan " . ucfirst($sleeve);
                        }
                        if (!empty($reqDetails['collar_model'])) {
                            $collar = str_replace('_', ' ', (string)$reqDetails['collar_model']);
                            $specs[] = "Kerah " . ucfirst($collar);
                        }
                        if (count($specs) > 0) {
                            $details[] = implode(', ', $specs);
                        }

                        if (!empty($reqDetails['note'])) {
                            $details[] = "Catatan: " . $reqDetails['note'];
                        }

                        $extra = count($details) > 0 ? " — " . implode(' | ', $details) : "";
                        $options[$item->id] = "{$size}{$qty}{$extra}";
                    }
                    return $options;
                })
                ->visible(fn ($get) => !empty($get('selected_product_name')))
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $item = OrderItem::find($state);
                        if ($item) {
                            $sizeKey = "Size " . ($item->size ?? 'All Size');
                            if (!empty($item->recipient_name)) {
                                $sizeKey .= " (Nama: {$item->recipient_name})";
                            }
                            $set('size_breakdown', [
                                $sizeKey => 1,
                            ]);
                            $set('quantity', 1);
                        }
                    }
                }),

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
                    'repair' => '🛠️ Perbaikan (Tukang Kerjakan Ulang Divisi/Tahap Tertentu)',
                    'remake' => '🏭 Buat Baru (Produksi Ulang Seluruhnya dari Awal)',
                ])
                ->required()
                ->reactive()
                ->default('repair'),

            Select::make('target_stage')
                ->label('Kirim Kembali Ke Tahap Mana? (Untuk Perbaikan)')
                ->options(function ($get) {
                    $itemId = $get('order_item_id');
                    $options = [
                        'QC_PERSIAPAN'  => '🔍 QC Persiapan Bahan',
                        'Potong'        => '✂️ Divisi Potong',
                        'Bordir/Sablon' => '🧵 Divisi Bordir / Sablon',
                        'Jahit'         => '🪡 Divisi Jahit',
                        'Finishing'     => '✨ Divisi Finishing / Packing',
                        'QC_AKHIR'      => '🏁 QC Akhir / Pemeriksaan Final',
                    ];

                    if ($itemId) {
                        $item = OrderItem::find($itemId);
                        if ($item) {
                            $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
                                ->where('order_item_id', $item->id)
                                ->pluck('stage_name')
                                ->unique();
                            foreach ($tasks as $stg) {
                                if (!isset($options[$stg])) {
                                    $options[$stg] = '🛠️ Divisi ' . $stg;
                                }
                            }
                        }
                    }

                    return $options;
                })
                ->visible(fn ($get) => $get('action_type') === 'repair')
                ->required(fn ($get) => $get('action_type') === 'repair')
                ->helperText('Pilih tahap/divisi kerja yang bertanggung jawab untuk melakukan perbaikan.'),

            KeyValue::make('size_breakdown')
                ->label('Rincian Ukuran / Nama Khusus Penerima yang Diretur')
                ->keyLabel('Ukuran / Nama Penerima / Identitas Khusus')
                ->keyPlaceholder('Contoh: Size S (Nama: Andre)')
                ->valuePlaceholder('1')
                ->helperText(function ($get) {
                    $itemId = $get('order_item_id');
                    $baseHelper = '💡 Anda bebas mengetik ukuran atau nama pendaftar khusus. Contoh: "Size S (Nama: Andre)" -> 1 pcs.';

                    if (!$itemId) return $baseHelper;

                    $item = OrderItem::find($itemId);
                    if (!$item) return $baseHelper;

                    $groupItems = $item->getItemsInGroup();
                    $sizes = [];
                    foreach ($groupItems as $gi) {
                        $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                        $sizes[] = ($sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz) . " ({$gi->quantity} pcs)";
                    }
                    return $baseHelper . ' | Ukuran umum dalam item ini: ' . implode(', ', $sizes);
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
                ->label('Total Jumlah Pcs yang Diretur')
                ->required()
                ->numeric()
                ->default(1)
                ->minValue(1),

            Select::make('status')
                ->label('Status Antrian Retur')
                ->options([
                    'pending'  => '⏳ Pending (Masuk Antrian Tukang)',
                    'diproses' => '🔨 Diproses (Sedang Dikerjakan Tukang)',
                    'selesai'  => '✅ Selesai (Retur Selesai)',
                ])
                ->required()
                ->default('pending'),

            FileUpload::make('photo_path')
                ->label('Foto Bukti Kerusakan / Cacat Baju')
                ->image()
                ->directory('order-returns')
                ->maxSize(5120)
                ->columnSpanFull(),

            Textarea::make('items_description')
                ->label('Catatan Detail Kerusakan & Petunjuk Instruksi Tukang')
                ->placeholder('Jelaskan kecacatan secara spesifik. Contoh: Baju Size S dengan nama "Andre", bordir nama di dada kiri miring 2 cm. Tolong dibongkar dan dibordir ulang nama Andre dengan rapi.')
                ->rows(3)
                ->required()
                ->helperText('Petunjuk ini akan langsung terkirim ke WhatsApp tukang dan muncul mencolok di portal tugas tukang.')
                ->columnSpanFull(),

            Textarea::make('reason')
                ->label('Alasan & Rincian Retur')
                ->placeholder('Tuliskan alasan retur atau instruksi perbaikan untuk tukang...')
                ->columnSpanFull(),
        ]);
    }
}
