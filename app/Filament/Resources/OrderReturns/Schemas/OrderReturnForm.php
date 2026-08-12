<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
            // 1. Pilih Produk Utama Pesanan
            Select::make('selected_product_name')
                ->label('1. Pilih Produk Pesanan')
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
                            'custom' => 'Produksi',
                            'non_produksi' => 'Non-Produksi',
                            'jasa' => 'Jasa',
                            default => 'Produksi',
                        };
                        $options[$pName] = "{$pName} ({$cat} — Total: {$totQty} pcs)";
                    }
                    return $options;
                })
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('order_item_id', null))
                ->helperText('Pilih nama produk dari pesanan ini yang mengalami komplain/cacat.'),

            // 2. Grid Rincian Item / Ukuran yang Diretur
            Repeater::make('multi_size_items')
                ->label('2. Pilih Ukuran & Input Jumlah Pcs Diretur')
                ->visible(fn ($get) => !empty($get('selected_product_name')))
                ->schema([
                    Select::make('order_item_id')
                        ->label('Ukuran / Varian Item')
                        ->options(function ($get, $record) {
                            $pName = $get('../../selected_product_name');
                            $orderId = $get('../../order_id');
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

                            $groupedOptions = [];

                            foreach ($items as $item) {
                                $reqDetails = $item->size_and_request_details ?? [];
                                $groupName = !empty($reqDetails['group_label']) 
                                    ? "📦 Varian: " . $reqDetails['group_label']
                                    : "📦 Varian Utama";

                                if (!empty($reqDetails['gender'])) {
                                    $groupName .= " (" . ($reqDetails['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') . ")";
                                }

                                if ($item->is_addition) {
                                    $groupName = "➕ Batch Penambahan Baru";
                                    if (!empty($reqDetails['group_label'])) {
                                        $groupName .= " — " . $reqDetails['group_label'];
                                    }
                                }

                                $sz = $item->size ? "Ukuran {$item->size}" : "Tanpa Ukuran";
                                $qty = " ({$item->quantity} pcs)";
                                
                                $details = [];
                                if (!empty($item->recipient_name)) {
                                    $details[] = "👤 Penerima: {$item->recipient_name}";
                                }

                                $specs = [];
                                if (!empty($reqDetails['sleeve_model'])) {
                                    $specs[] = "Lengan " . ucfirst(str_replace('_', ' ', (string)$reqDetails['sleeve_model']));
                                }
                                if (!empty($reqDetails['collar_model'])) {
                                    $specs[] = "Kerah " . ucfirst(str_replace('_', ' ', (string)$reqDetails['collar_model']));
                                }
                                if (count($specs) > 0) {
                                    $details[] = implode(', ', $specs);
                                }

                                $extra = count($details) > 0 ? " — " . implode(' | ', $details) : "";
                                $label = "{$sz}{$qty}{$extra}";

                                $groupedOptions[$groupName][$item->id] = $label;
                            }

                            return $groupedOptions;
                        })
                        ->required()
                        ->reactive()
                        ->searchable(),

                    TextInput::make('return_qty')
                        ->label('Jumlah Pcs Diretur')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(function ($get) {
                            $itemId = $get('order_item_id');
                            if (!$itemId) return null;
                            $item = OrderItem::find($itemId);
                            return $item ? (int)$item->quantity : null;
                        })
                        ->required(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel('+ Tambah Ukuran Diretur')
                ->itemLabel(fn (array $state): ?string => isset($state['return_qty']) ? "{$state['return_qty']} pcs diretur" : null)
                ->helperText('Untuk retur 1 ukuran: isi 1 baris di atas. Untuk retur banyak ukuran sekaligus: klik + Tambah Ukuran Diretur.'),

            // 4. Tindakan Retur (Perbaikan / Buat Baru)
            Select::make('action_type')
                ->label('4. Tindakan Retur')
                ->options([
                    'repair' => '🛠️ Perbaikan / Revisi (Dikerjakan ulang bagian tertentu)',
                    'remake' => '🏭 Buat Baru (Produksi ulang dari awal)',
                ])
                ->required()
                ->reactive()
                ->default('repair'),

            // 5. Divisi Tujuan Perbaikan
            CheckboxList::make('target_stages')
                ->label('5. Kirim Ke Divisi Mana? (Untuk Perbaikan)')
                ->options(function ($get) {
                    $itemId = $get('order_item_id');
                    $dbStages = \App\Models\ProductionStage::where('name', '!=', 'QC')
                        ->orderBy('order_sequence')
                        ->pluck('name')
                        ->toArray();

                    if (empty($dbStages)) {
                        $dbStages = ['Potong', 'Jahit', 'Kancing', 'Bordir/Sablon', 'Finishing'];
                    }

                    $item = $itemId ? OrderItem::find($itemId) : null;
                    $cat = $item?->production_category ?? 'produksi';

                    if ($cat === 'non_produksi' || $cat === 'jasa') {
                        $stages = array_filter($dbStages, fn($s) => in_array($s, ['Bordir/Sablon', 'Finishing']));
                    } else {
                        $stages = $dbStages;
                    }

                    $options = [];
                    foreach ($stages as $stg) {
                        $label = match($stg) {
                            'Potong'        => '✂️ Divisi Potong',
                            'Jahit'         => '🪡 Divisi Jahit',
                            'Kancing'       => '🔘 Divisi Kancing',
                            'Bordir/Sablon', 'Sablon/Bordir' => '🧵 Divisi Bordir / Sablon',
                            'Finishing'     => '✨ Divisi Finishing / Packing',
                            default         => '🛠️ Divisi ' . $stg,
                        };
                        $options[$stg] = $label;
                    }

                    return $options;
                })
                ->visible(fn ($get) => $get('action_type') === 'repair')
                ->required(fn ($get) => $get('action_type') === 'repair')
                ->helperText('Pilih divisi tukang yang akan memperbaiki barang ini.'),

            // 6. Penanggung Jawab / Garansi
            Select::make('responsibility_type')
                ->label('6. Garansi / Tanggung Jawab')
                ->options([
                    'store_guarantee' => '🛡️ Garansi Toko / Cacat Produksi (Gratis Customer)',
                    'customer_paid'   => '💳 Kesalahan Customer / Request Tambahan (Berbayar)',
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

            DatePicker::make('return_date')
                ->label('Tanggal Masuk Retur')
                ->default(now())
                ->required(),

            DatePicker::make('expected_pickup_date')
                ->label('Target Tanggal Selesai / Ambil')
                ->default(now()->addDays(3))
                ->required()
                ->helperText('Janji tanggal barang returan siap diambil customer.'),

            // 7. Detail Kerusakan & Catatan Perbaikan
            Textarea::make('items_description')
                ->label('7. Detail Cacat & Instruksi Perbaikan')
                ->placeholder('Jelaskan bagian mana yang cacat. Contoh: Baju Size S nama Andre, jahitan ketiak kanan lepas 3 cm. Tolong dijahit ulang dengan rapi.')
                ->rows(3)
                ->required()
                ->helperText('Instruksi ini akan langsung terkirim ke tukang yang bertugas.')
                ->columnSpanFull(),

            // 8. Foto Bukti Kerusakan
            FileUpload::make('photo_path')
                ->label('Foto Bukti Cacat / Kerusakan (Opsional)')
                ->image()
                ->directory('order-returns')
                ->maxSize(5120)
                ->columnSpanFull(),
        ]);
    }
}
