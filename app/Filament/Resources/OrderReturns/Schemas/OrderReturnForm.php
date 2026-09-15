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
use App\Models\Order;
use App\Models\OrderReturn;

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
        } else {
            $components[] = \Filament\Forms\Components\Hidden::make('order_id')
                ->default(function ($record) {
                    if ($record instanceof Order) {
                        return $record->id;
                    }
                    if ($record && isset($record->order_id)) {
                        return $record->order_id;
                    }
                    $routeRecord = request()->route()?->parameter('record');
                    if ($routeRecord instanceof Order) {
                        return $routeRecord->id;
                    }
                    if (is_numeric($routeRecord)) {
                        return (int) $routeRecord;
                    }
                    return null;
                });
        }

        $resolveOrderId = function ($get, $record) {
            $orderId = $get('order_id') ?? $get('../../order_id');
            if ($orderId) {
                return $orderId;
            }
            if ($record instanceof Order) {
                return $record->id;
            }
            if ($record && isset($record->order_id)) {
                return $record->order_id;
            }
            $routeRecord = request()->route()?->parameter('record');
            if ($routeRecord instanceof Order) {
                return $routeRecord->id;
            }
            if (is_numeric($routeRecord)) {
                return (int) $routeRecord;
            }
            return null;
        };

        return array_merge($components, [
            // Batch Retur Grouping
            Select::make('batch_number')
                ->label('📦 Batch Retur')
                ->options(function ($get, $record) use ($resolveOrderId) {
                    $orderId = $resolveOrderId($get, $record);
                    if (!$orderId) return [1 => 'Batch #1 (Retur Pertama)'];

                    $existingBatches = OrderReturn::withoutGlobalScopes()
                        ->where('order_id', $orderId)
                        ->distinct()
                        ->pluck('batch_number')
                        ->filter()
                        ->sort()
                        ->values()
                        ->toArray();

                    $options = [];
                    foreach ($existingBatches as $bNum) {
                        $undeliveredCount = OrderReturn::withoutGlobalScopes()
                            ->where('order_id', $orderId)
                            ->where('batch_number', $bNum)
                            ->whereNull('delivered_at')
                            ->count();
                        $statusLabel = $undeliveredCount > 0 ? ' [Sedang Berjalan]' : ' [Sudah Diserahkan]';
                        $options[$bNum] = "Batch #{$bNum}{$statusLabel}";
                    }

                    $nextBatch = (!empty($existingBatches) ? max($existingBatches) : 0) + 1;
                    if (!isset($options[$nextBatch])) {
                        $options[$nextBatch] = "➕ Buat Batch #{$nextBatch} Baru (Retur Susulan)";
                    }

                    return $options;
                })
                ->default(function ($get, $record) use ($resolveOrderId) {
                    $orderId = $resolveOrderId($get, $record);
                    if (!$orderId) return 1;

                    // Default to latest active (undelivered) batch, or new batch if all delivered
                    $activeBatch = OrderReturn::withoutGlobalScopes()
                        ->where('order_id', $orderId)
                        ->whereNull('delivered_at')
                        ->orderBy('batch_number', 'desc')
                        ->value('batch_number');

                    if ($activeBatch) {
                        return (int) $activeBatch;
                    }

                    $maxBatch = OrderReturn::withoutGlobalScopes()
                        ->where('order_id', $orderId)
                        ->max('batch_number');

                    return $maxBatch ? ((int)$maxBatch + 1) : 1;
                })
                ->required()
                ->helperText('Pilih Batch aktif untuk menggabungkan retur ini, atau pilih "Buat Batch Baru" jika ini komplain susulan.'),

            // 1. Pilih Produk Utama Pesanan
            Select::make('selected_product_name')
                ->label('1. Pilih Produk Pesanan')
                ->options(function ($get, $record) use ($resolveOrderId) {
                    $orderId = $resolveOrderId($get, $record);
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
                ->afterStateUpdated(function (callable $set) {
                    $set('multi_size_items', [
                        ['order_item_id' => null, 'return_qty' => 1],
                    ]);
                })
                ->helperText('Pilih nama produk dari pesanan ini yang mengalami komplain/cacat.'),

            // 2. Grid Rincian Item / Ukuran yang Diretur
            Repeater::make('multi_size_items')
                ->label('2. Pilih Ukuran & Input Jumlah Pcs Diretur')
                ->visible(fn ($get) => !empty($get('selected_product_name')))
                ->default([
                    ['order_item_id' => null, 'return_qty' => 1],
                ])
                ->defaultItems(1)
                ->minItems(1)
                ->schema([
                    Select::make('order_item_id')
                        ->label('Ukuran / Varian Item')
                        ->options(function ($get, $record) use ($resolveOrderId) {
                            $pName = $get('../../selected_product_name');
                            $orderId = $resolveOrderId($get, $record);
                            if (!$orderId || !$pName) return [];

                            $items = OrderItem::where('order_id', $orderId)
                                ->where('product_name', $pName)
                                ->get();

                            $groupedOptions = [];

                            foreach ($items as $item) {
                                $reqDetails = $item->size_and_request_details ?? [];
                                
                                $prefix = $item->is_addition ? "➕ Batch Penambahan" : "📦 Batch Utama";
                                $vLabel = !empty($reqDetails['group_label']) ? $reqDetails['group_label'] : "Varian Standard";
                                $gender = !empty($reqDetails['gender']) ? " (" . ($reqDetails['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') . ")" : "";

                                $groupName = "{$prefix} — Varian: {$vLabel}{$gender}";

                                $sz = $item->size ? "Ukuran {$item->size}" : "Tanpa Ukuran";
                                $qty = " ({$item->quantity} pcs)";
                                $extra = !empty($item->recipient_name) ? " — 👤 Penerima: {$item->recipient_name}" : "";
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
                    $multiItems = $get('multi_size_items') ?? [];
                    $firstItemId = null;
                    if (!empty($multiItems) && is_array($multiItems)) {
                        $firstItemId = $multiItems[0]['order_item_id'] ?? null;
                    }
                    if (!$firstItemId) {
                        $firstItemId = $get('order_item_id');
                    }

                    $dbStages = \App\Models\ProductionStage::where('name', '!=', 'QC')
                        ->orderBy('order_sequence')
                        ->pluck('name')
                        ->toArray();

                    if (empty($dbStages)) {
                        $dbStages = ['Potong', 'Jahit', 'Kancing', 'Bordir/Sablon', 'Finishing'];
                    }

                    $item = $firstItemId ? OrderItem::find($firstItemId) : null;
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
                ->disk('public')
                ->visibility('public')
                ->directory('order-returns')
                ->maxSize(5120)
                ->columnSpanFull(),
        ]);
    }

    public static function processReturnCreation(Order $order, array $data): OrderReturn
    {
        $data['shop_id'] = $order->shop_id ?? \Filament\Facades\Filament::getTenant()?->id;
        $data['order_id'] = $order->id;

        $targetStages = $data['target_stages'] ?? [];
        $multiItems = $data['multi_size_items'] ?? [];

        unset($data['target_stages'], $data['multi_size_items'], $data['selected_product_name'], $data['selection_mode']);

        $retur = new OrderReturn();
        $retur->fill($data);
        $retur->target_stages = $targetStages;
        $retur->multi_size_items = $multiItems;

        if (empty($retur->order_item_id) && !empty($multiItems)) {
            $firstItemId = $multiItems[0]['order_item_id'] ?? null;
            if ($firstItemId) {
                $retur->order_item_id = $firstItemId;
            }
        }

        if (empty($retur->quantity) && !empty($multiItems)) {
            $totalQty = 0;
            foreach ($multiItems as $item) {
                $totalQty += (int)($item['return_qty'] ?? 1);
            }
            $retur->quantity = $totalQty > 0 ? $totalQty : 1;
        }

        $retur->save();

        return $retur;
    }
}
