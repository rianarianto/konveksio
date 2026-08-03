<?php

namespace App\Filament\Resources\OrderReturns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

            // 2. Pilih Ukuran / Varian Spesifik yang Diretur
            Select::make('order_item_id')
                ->label('2. Pilih Ukuran / Varian yang Diretur')
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
                    $labelCounts = [];
                    $itemLabels = [];

                    foreach ($items as $item) {
                        $sz = $item->size ? "Ukuran {$item->size}" : "Tanpa Ukuran";
                        $qty = " ({$item->quantity} pcs)";
                        
                        $details = [];
                        $reqDetails = $item->size_and_request_details ?? [];
                        
                        if (!empty($reqDetails['group_label'])) {
                            $details[] = "VARIAN: " . strtoupper($reqDetails['group_label']);
                        }

                        if (!empty($reqDetails['gender'])) {
                            $details[] = "JK: " . ($reqDetails['gender'] === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN');
                        }

                        if (!empty($item->recipient_name)) {
                            $details[] = "PEMESAN: {$item->recipient_name}";
                        }

                        $specs = [];
                        if (!empty($reqDetails['sleeve_model'])) {
                            $sleeve = str_replace('_', ' ', (string)$reqDetails['sleeve_model']);
                            $specs[] = "Lengan " . ucfirst($sleeve);
                        }
                        if (!empty($reqDetails['pocket_model'])) {
                            $pocket = str_replace('_', ' ', (string)$reqDetails['pocket_model']);
                            $specs[] = "Saku " . ucfirst($pocket);
                        }
                        if (!empty($reqDetails['button_model'])) {
                            $button = str_replace('_', ' ', (string)$reqDetails['button_model']);
                            $specs[] = "Kancing " . ucfirst($button);
                        }
                        if (!empty($reqDetails['collar_model'])) {
                            $collar = str_replace('_', ' ', (string)$reqDetails['collar_model']);
                            $specs[] = "Kerah " . ucfirst($collar);
                        }
                        if (!empty($reqDetails['clothing_model'])) {
                            $model = str_replace('_', ' ', (string)$reqDetails['clothing_model']);
                            $specs[] = "Model " . ucfirst($model);
                        }

                        if (count($specs) > 0) {
                            $details[] = implode(', ', $specs);
                        }

                        if (!empty($reqDetails['spec_notes'])) {
                            $details[] = "Catatan: " . $reqDetails['spec_notes'];
                        }

                        if ($item->is_addition) {
                            $details[] = "➕ Item Tambahan";
                        }

                        $extra = count($details) > 0 ? " — " . implode(' | ', $details) : "";
                        $baseLabel = "{$sz}{$qty}{$extra}";

                        $itemLabels[$item->id] = [
                            'baseLabel' => $baseLabel,
                            'is_addition' => $item->is_addition,
                        ];

                        $labelCounts[$baseLabel] = ($labelCounts[$baseLabel] ?? 0) + 1;
                    }

                    $seenCounts = [];
                    foreach ($itemLabels as $itemId => $data) {
                        $baseLabel = $data['baseLabel'];
                        if (($labelCounts[$baseLabel] ?? 0) > 1) {
                            $seenCounts[$baseLabel] = ($seenCounts[$baseLabel] ?? 0) + 1;
                            $idx = $seenCounts[$baseLabel];
                            $tag = $idx === 1 ? " [Item Awal]" : " ➕ [Penambahan #{$idx}]";
                            $options[$itemId] = "{$baseLabel}{$tag}";
                        } else {
                            $options[$itemId] = $baseLabel;
                        }
                    }

                    return $options;
                })
                ->visible(fn ($get) => !empty($get('selected_product_name')))
                ->searchable()
                ->required()
                ->reactive()
                ->helperText('Pilih ukuran spesifik baju yang mengalami kerusakan.'),

            // 3. Jumlah Pcs yang Diretur
            TextInput::make('quantity')
                ->label('3. Jumlah Pcs yang Diretur')
                ->required()
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->helperText('Jumlah pcs/potong yang cacat dari ukuran tersebut.'),

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
            Select::make('target_stage')
                ->label('5. Kirim Ke Divisi Mana? (Untuk Perbaikan)')
                ->options(function ($get) {
                    $itemId = $get('order_item_id');
                    if (!$itemId) {
                        return [
                            'QC_PERSIAPAN'  => '🔍 QC Persiapan Bahan',
                            'Potong'        => '✂️ Divisi Potong',
                            'Bordir/Sablon' => '🧵 Divisi Bordir / Sablon',
                            'Jahit'         => '🪡 Divisi Jahit',
                            'Finishing'     => '✨ Divisi Finishing / Packing',
                            'QC_AKHIR'      => '🏁 QC Akhir / Pemeriksaan Final',
                        ];
                    }

                    $item = OrderItem::find($itemId);
                    if (!$item) return [];

                    $groupItemIds = $item->getItemsInGroup()->pluck('id');
                    $wo = \App\Models\WorkOrder::whereIn('order_item_id', $groupItemIds)->first();
                    $options = [];

                    if (!$wo || ($wo && $wo->has_qc_prep)) {
                        $options['QC_PERSIAPAN'] = '🔍 QC Persiapan Bahan';
                    }

                    $stages = [];
                    if ($wo && !empty($wo->stage_sequence)) {
                        $stages = $wo->stage_sequence;
                    } else {
                        $stages = \App\Models\ProductionTask::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->pluck('stage_name')
                            ->unique()
                            ->toArray();
                    }

                    if (empty($stages)) {
                        $cat = $item->production_category ?? 'produksi';
                        if ($cat === 'non_produksi' || $cat === 'jasa') {
                            $stages = ['Bordir/Sablon', 'Finishing'];
                        } else {
                            $stages = ['Potong', 'Bordir/Sablon', 'Jahit', 'Finishing'];
                        }
                    }

                    foreach ($stages as $stg) {
                        if (in_array($stg, ['QC_PERSIAPAN', 'QC_AKHIR'])) continue;
                        $label = match($stg) {
                            'Potong'        => '✂️ Divisi Potong',
                            'Bordir/Sablon' => '🧵 Divisi Bordir / Sablon',
                            'Sablon/Bordir' => '🧵 Divisi Bordir / Sablon',
                            'Jahit'         => '🪡 Divisi Jahit',
                            'Finishing'     => '✨ Divisi Finishing / Packing',
                            'Kancing'       => '🔘 Divisi Kancing',
                            default         => '🛠️ Divisi ' . $stg,
                        };
                        $options[$stg] = $label;
                    }

                    if (!$wo || ($wo && ($wo->has_qc_selesai ?? true))) {
                        $options['QC_AKHIR'] = '🏁 QC Akhir / Pemeriksaan Final';
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
