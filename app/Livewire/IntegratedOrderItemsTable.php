<?php

namespace App\Livewire;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Material;
use App\Models\StoreSize;
use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Livewire\Component;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class IntegratedOrderItemsTable extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public Order $order;
    public ?string $notes = null;
    public ?array $bulkFormDraft = null;

    public function getBulkDraft(): ?array
    {
        return $this->bulkFormDraft ?? session("bulk_draft_{$this->order->id}");
    }

    public function updatedMountedTableActionsData(): void
    {
        if (!empty($this->mountedTableActionsData)) {
            $firstData = reset($this->mountedTableActionsData);
            if (is_array($firstData) && !empty($firstData)) {
                $this->bulkFormDraft = $firstData;
                session(["bulk_draft_{$this->order->id}" => $firstData]);
            }
        }
    }

    public function clearBulkFormDraft(): void
    {
        $this->bulkFormDraft = null;
        session()->forget("bulk_draft_{$this->order->id}");
        $this->unmountTableAction();
        \Filament\Notifications\Notification::make()->title('Draf form dibersihkan')->info()->send();
    }

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->notes = $order->notes;
    }

    public function updatedNotes($value): void
    {
        $this->order->update(['notes' => $value]);
    }

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->id;

        // Prepare options
        $sizeOptions = StoreSize::where('shop_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'name')
            ->toArray();

        $bahanOptions = Material::where('shop_id', $tenantId)
            ->pluck('name', 'id')
            ->toArray();

        $categoryOptions = [
            'produksi' => 'Produksi',
            'non_produksi' => 'Non-Produksi',
            'jasa' => 'Jasa',
        ];

        $genderOptions = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
        
        $shop = \App\Models\Shop::withoutGlobalScope(\App\Models\Scopes\ShopScope::class)->find($tenantId);
        $savedSpecs = $shop?->production_spec_options ?? [];

        // Sleeve (Lengan)
        $sleeveOptions = [];
        if (!empty($savedSpecs['sleeve_options'])) {
            foreach ($savedSpecs['sleeve_options'] as $opt) {
                $sleeveOptions[Str::slug($opt, '_')] = $opt;
            }
        } else {
            $sleeveOptions = [
                'pendek' => 'Pendek',
                'panjang' => 'Panjang',
                '3/4' => '3/4',
            ];
        }

        // Pocket (Saku)
        $pocketOptions = [];
        if (!empty($savedSpecs['pocket_options'])) {
            foreach ($savedSpecs['pocket_options'] as $opt) {
                $pocketOptions[Str::slug($opt, '_')] = $opt;
            }
        } else {
            $pocketOptions = [
                'tanpa_saku' => 'Tanpa Saku',
                'jalan_tol' => 'Jalan Tol',
                'semi_kelewang' => 'Semi Kelewang',
                'kelewang' => 'Kelewang',
                'saku_tabuk' => 'Saku Tabuk',
                'patah_suduik' => 'Patah Suduik',
            ];
        }

        // Button (Kancing)
        $buttonOptions = [];
        if (!empty($savedSpecs['button_options'])) {
            foreach ($savedSpecs['button_options'] as $opt) {
                $buttonOptions[Str::slug($opt, '_')] = $opt;
            }
        } else {
            $buttonOptions = [
                'biasa' => 'Biasa',
                'tutup' => 'Tutup',
            ];
        }

        // Collar (Kerah)
        $collarOptions = [];
        if (!empty($savedSpecs['collar_options'])) {
            foreach ($savedSpecs['collar_options'] as $opt) {
                $collarOptions[Str::slug($opt, '_')] = $opt;
            }
        } else {
            $collarOptions = [
                'shanghai' => 'Shanghai',
                'kemeja' => 'Kemeja',
                'ramora' => 'Ramora',
            ];
        }

        $modelOptions = ['biasa' => 'Kemeja Biasa', 'tunic' => 'Tunik'];

        return $table
            ->query(
                OrderItem::withoutGlobalScopes()
                    ->fromSub(
                        OrderItem::withoutGlobalScopes()
                            ->fromSub(
                        OrderItem::query()
                            ->where('order_id', $this->order->id)
                            ->leftJoin('materials', 'order_items.bahan_id', '=', 'materials.id')
                            ->leftJoin('material_variants', function($join) {
                                $join->on(\Illuminate\Support\Facades\DB::raw("JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.material_variant_id'))"), '=', 'material_variants.id');
                            })
                            ->leftJoin('product_variants', function($join) {
                                $join->on(\Illuminate\Support\Facades\DB::raw("JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.product_variant_id'))"), '=', 'product_variants.id');
                            })
                            ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
                            ->select('order_items.*')
                            ->selectRaw('materials.name as bahan_name')
                            ->selectRaw('COALESCE(material_variants.color_name, product_variants.color_name) as varian_warna')
                            ->selectRaw('COALESCE(material_variants.color_code, product_variants.color_code) as varian_kode_warna')
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.gender')), 'L'), null) as gender")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sleeve_model')), 'pendek'), null) as sleeve_model")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.pocket_model')), 'tanpa_saku'), null) as pocket_model")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.button_model')), 'biasa'), null) as button_model")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.collar_model')), 'biasa'), null) as collar_model")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.model')), 'biasa'), null) as model")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sablon_jenis')), null) as sablon_jenis")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sablon_lokasi')), null) as sablon_lokasi")
                            ->selectRaw("IF(production_category IN ('produksi', 'custom'), JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sablon_keterangan')), null) as sablon_keterangan")
                            ->selectRaw("
                                CONCAT(
                                    product_name, IF(production_category IN ('non_produksi', 'jasa'), '', CONCAT(' (', IF(order_items.size = 'Custom', 'Ukur Badan', 'Size Toko'), ')')), ' | ',
                                    CASE 
                                        WHEN production_category = 'custom' THEN 'Produksi (Ukur)' 
                                        WHEN production_category = 'non_produksi' THEN 'Non-Produksi'
                                        WHEN production_category = 'jasa' THEN 'Jasa'
                                        ELSE 'Produksi' 
                                    END, ' | ',
                                    CASE 
                                        WHEN production_category = 'non_produksi' THEN COALESCE(products.name, 'Tanpa Katalog')
                                        WHEN production_category = 'jasa' THEN 'Layanan Jasa'
                                        ELSE COALESCE(materials.name, 'Tanpa Bahan')
                                    END,
                                    ' | ',
                                    COALESCE(material_variants.color_name, product_variants.color_name, 'Tanpa Warna')
                                ) as item_group_identity
                            "),
                        'raw_items'
                    )
                    ->select([
                        'product_name',
                        'production_category',
                        'bahan_id',
                        'varian_warna',
                        'gender',
                        'sleeve_model',
                        'pocket_model',
                        'button_model',
                        'collar_model',
                        'model',
                        'sablon_jenis',
                        'sablon_lokasi',
                        'sablon_keterangan',
                    ])
                    ->selectRaw('MIN(id) as id')
                    ->selectRaw('MAX(order_id) as order_id')
                    ->selectRaw('MAX(bahan_name) as bahan_name')
                    ->selectRaw('MAX(recipient_name) as recipient_name')
                    ->selectRaw('MAX(size) as size')
                    ->selectRaw('MIN(price) as price')
                    ->selectRaw('ANY_VALUE(size_and_request_details) as size_and_request_details')
                    ->selectRaw('MAX(created_at) as created_at')
                    ->selectRaw('MAX(updated_at) as updated_at')
                    ->selectRaw('SUM(quantity) as quantity')
                    ->selectRaw("GROUP_CONCAT(CONCAT(size, ': ', quantity) ORDER BY size SEPARATOR ', ') as sizes_summary")
                    ->selectRaw('MAX(item_group_identity) as item_group_identity')
                    ->groupBy([
                        'product_name',
                        'production_category',
                        'bahan_id',
                        'varian_warna',
                        'gender',
                        'sleeve_model',
                        'pocket_model',
                        'button_model',
                        'collar_model',
                        'model',
                        'sablon_jenis',
                        'sablon_lokasi',
                        'sablon_keterangan',
                        \Illuminate\Support\Facades\DB::raw("(IF(raw_items.size = 'Custom', id, 0))")
                    ]),
                    'order_items'
                )
             )
            ->columns([
                TextColumn::make('product_name')
                    ->label('Penerima / Tipe')
                    ->searchable()
                    ->sortable()
                    ->view('filament.tables.columns.recipient-name-input'),

                TextColumn::make('production_category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(OrderItem $record) => match ($record->production_category) {
                        'custom' => 'warning',
                        'non_produksi' => 'info',
                        'jasa' => 'success',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'custom' => '🧵 Custom (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Produksi',
                    })
                    ->sortable(),

                 TextColumn::make('bahan_warna')
                    ->label('Bahan & Warna')
                    ->state(function(OrderItem $record) {
                        if ($record->production_category === 'jasa') return '-';
                        $color = $record->varian_warna ?: 'Tanpa Warna';
                        if (!empty($record->varian_kode_warna)) {
                            $color .= " ({$record->varian_kode_warna})";
                        }
                        if ($record->production_category === 'non_produksi') {
                            return "{$record->product_name} ({$color})";
                        }
                        $bahan = $record->bahan_name ?: 'Tanpa Bahan';
                        return "{$bahan} ({$color})";
                    })
                    ->description(function(OrderItem $record) {
                        $details = $record->size_and_request_details ?? [];
                        $totalStockUsed = (float) ($details['stock_qty_used'] ?? 0);
                        if ($totalStockUsed > 0) {
                            $unit = 'pcs';
                            if ($record->production_category === 'produksi' && $record->bahan_id) {
                                $unit = \App\Models\Material::find($record->bahan_id)?->unit ?? 'm';
                            }
                            $formatted = number_format($totalStockUsed, ($totalStockUsed == (int)$totalStockUsed ? 0 : 1), ',', '.');
                            return "📦 Stok Terpakai: {$formatted} {$unit}";
                        }
                        return null;
                    }),

                 TextColumn::make('specifications')
                    ->label('Spesifikasi')
                    ->state(function(OrderItem $record) use ($genderOptions, $sleeveOptions, $collarOptions) {
                        $details = $record->size_and_request_details ?? [];
                        $sablon = $details['sablon_jenis'] ?? null;
                        $groupLabel = $details['group_label'] ?? null;
                        
                        $isProduksi = in_array($record->production_category, ['produksi', 'custom']);
                        
                        $summary = "";
                        if ($isProduksi) {
                            $gender = $genderOptions[$record->gender] ?? $record->gender ?? 'Laki-laki';
                            $sleeve = $sleeveOptions[$record->sleeve_model] ?? $record->sleeve_model ?? 'pendek';
                            $collar = $collarOptions[$record->collar_model] ?? $record->collar_model ?? 'biasa';
                            $prefix = filled($groupLabel) ? "🏷️ {$groupLabel} ({$gender})" : $gender;
                            $summary = "{$prefix} | Lengan {$sleeve}, Kerah {$collar}";
                        }
                        
                        if ($sablon && $sablon !== 'Tanpa Sablon/Bordir') {
                            if ($isProduksi) {
                                $summary .= " (🎨 +{$sablon})";
                            } else {
                                $lokasi = $details['sablon_lokasi'] ?? '';
                                $summary = $lokasi ? "🎨 {$sablon} - {$lokasi}" : "🎨 {$sablon}";
                            }
                        }
                        
                        return $summary ?: '-';
                    })
                    ->badge(fn(OrderItem $record) => in_array($record->production_category, ['produksi', 'custom']) || !empty($record->size_and_request_details['sablon_jenis']))
                    ->color('gray')
                    ->icon(fn(OrderItem $record) => (in_array($record->production_category, ['produksi', 'custom']) || !empty($record->size_and_request_details['sablon_jenis'])) ? 'heroicon-m-magnifying-glass-circle' : null)
                    ->iconPosition('after')
                    ->tooltip(fn(OrderItem $record) => (in_array($record->production_category, ['produksi', 'custom']) || !empty($record->size_and_request_details['sablon_jenis'])) ? 'Klik untuk detail lengkap' : null)
                    ->action(
                        Action::make('view_spec_details')
                            ->disabled(fn(OrderItem $record) => !(in_array($record->production_category, ['produksi', 'custom']) || !empty($record->size_and_request_details['sablon_jenis'])))
                            ->modalHeading('Detail Spesifikasi Produk')
                            ->modalWidth('md')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn($action) => $action->label('Tutup')->color('primary'))
                            ->form(function(OrderItem $record) use ($genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions, $collarOptions, $modelOptions) {
                                $gender = $genderOptions[$record->gender] ?? $record->gender ?? 'L';
                                $sleeve = $sleeveOptions[$record->sleeve_model] ?? $record->sleeve_model ?? 'pendek';
                                $pocket = $pocketOptions[$record->pocket_model] ?? $record->pocket_model ?? 'tanpa_saku';
                                $button = $buttonOptions[$record->button_model] ?? $record->button_model ?? 'biasa';
                                $collar = $collarOptions[$record->collar_model] ?? $record->collar_model ?? 'biasa';
                                $model = $modelOptions[$record->model] ?? $record->model ?? 'biasa';

                                $details = $record->size_and_request_details ?? [];
                                $sablon = $details['sablon_jenis'] ?? null;
                                $lokasi = $details['sablon_lokasi'] ?? null;
                                $ket = $details['sablon_keterangan'] ?? null;

                                if (!$sablon && !$lokasi && !$ket) {
                                    $groupItem = OrderItem::where('order_id', $record->order_id)
                                        ->where('product_name', $record->product_name)
                                        ->get()
                                        ->first(fn($i) => filled($i->size_and_request_details['sablon_jenis'] ?? null) || filled($i->size_and_request_details['sablon_lokasi'] ?? null) || filled($i->size_and_request_details['sablon_keterangan'] ?? null));
                                    if ($groupItem) {
                                        $gDetails = $groupItem->size_and_request_details ?? [];
                                        $sablon = $gDetails['sablon_jenis'] ?? null;
                                        $lokasi = $gDetails['sablon_lokasi'] ?? null;
                                        $ket = $gDetails['sablon_keterangan'] ?? null;
                                    }
                                }

                                $sablon = $sablon ?: '-';
                                $lokasi = $lokasi ?: '-';
                                $ket = $ket ?: '-';

                                $isProduksi = in_array($record->production_category, ['produksi', 'custom']);
                                $isCustom = $record->size === 'Custom';
                                $ld = $details['LD'] ?? '-';
                                $pb = $details['PB'] ?? '-';
                                $pl = $details['PL'] ?? '-';
                                $lb = $details['LB'] ?? '-';
                                $lp = $details['LP'] ?? '-';
                                $lph = $details['LPh'] ?? '-';
                                $note = $details['note'] ?? '-';
                                $groupLabel = $details['group_label'] ?? null;
                                $specNotes = $details['spec_notes'] ?? null;

                                return [
                                    Grid::make(['default' => 2, 'sm' => 2, 'md' => 3])->schema([
                                        Placeholder::make('group_label')->label('Nama Pengelompokan / Label')->content($groupLabel ?: '-')->columnSpanFull()->visible($isProduksi && filled($groupLabel)),
                                        Placeholder::make('recipient_name')->label('Nama Penerima')->content($record->recipient_name ?: '-')->visible($isCustom && filled($record->recipient_name)),
                                        Placeholder::make('gender')->label('Gender / JK')->content($gender)->visible($isProduksi),
                                        Placeholder::make('sleeve')->label('Lengan')->content("Lengan " . $sleeve)->visible($isProduksi),
                                        Placeholder::make('pocket')->label('Saku')->content($pocket)->visible($isProduksi),
                                        Placeholder::make('button')->label('Kancing')->content("Kancing " . $button)->visible($isProduksi),
                                        Placeholder::make('collar')->label('Kerah')->content("Kerah " . $collar)->visible($isProduksi),
                                        Placeholder::make('model')->label('Model')->content($model)->visible($isProduksi),
                                        Placeholder::make('spec_notes')->label('Catatan Khusus Spesifikasi')->content($specNotes)->columnSpanFull()->visible($isProduksi && filled($specNotes)),
                                        
                                        Section::make('Detail Ukuran Badan (Custom)')
                                            ->visible($isCustom)
                                            ->columnSpanFull()
                                            ->columns(['default' => 3, 'sm' => 3, 'md' => 6])
                                            ->compact()
                                            ->schema([
                                                Placeholder::make('ld_view')->label('LD')->content($ld ? $ld . " cm" : '-'),
                                                Placeholder::make('pb_view')->label('PB')->content($pb ? $pb . " cm" : '-'),
                                                Placeholder::make('pl_view')->label('PL')->content($pl ? $pl . " cm" : '-'),
                                                Placeholder::make('lb_view')->label('LB')->content($lb ? $lb . " cm" : '-'),
                                                Placeholder::make('lp_view')->label('LP')->content($lp ? $lp . " cm" : '-'),
                                                Placeholder::make('lph_view')->label('LPh')->content($lph ? $lph . " cm" : '-'),
                                                Placeholder::make('note_view')->label('Catatan Khusus Ukuran')->content($note ?: '-')->columnSpanFull(),
                                            ]),

                                        Section::make('Desain Sablon / Bordir')
                                            ->columnSpanFull()
                                            ->columns(['default' => 2, 'sm' => 2, 'md' => 3])
                                            ->compact()
                                            ->schema([
                                                Placeholder::make('sablon_jenis')->label('Teknik')->content($sablon),
                                                Placeholder::make('sablon_lokasi')->label('Titik Lokasi')->content($lokasi),
                                                Placeholder::make('sablon_ket')->label('Keterangan Desain')->content($ket)->columnSpanFull(),
                                            ]),
                                    ]),
                                ];
                            })
                    ),

                TextColumn::make('sizes_summary')
                    ->label('Ukuran & Qty')
                    ->badge()
                    ->color('primary')
                    ->state(function(OrderItem $record) {
                        if ($record->size === 'Custom') {
                            return 'Custom';
                        }
                        $summary = $record->sizes_summary;
                        if (blank($summary)) {
                            return '-';
                        }
                        
                        $parts = explode(',', $summary);
                        $sizeMap = [];
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (blank($part)) continue;
                            
                            $subParts = explode(':', $part);
                            if (count($subParts) === 2) {
                                $sz = trim($subParts[0]);
                                $qty = (int) trim($subParts[1]);
                                $sizeMap[$sz] = ($sizeMap[$sz] ?? 0) + $qty;
                            }
                        }
                        
                        $formattedParts = [];
                        foreach ($sizeMap as $sz => $qty) {
                            $formattedParts[] = "{$sz}: {$qty}";
                        }
                        
                        return implode(', ', $formattedParts);
                    })
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('Total Qty')
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable()
                    ->formatStateUsing(fn($state) => (int)$state === 0 ? '⚠️ 0 Pcs (Belum Lengkap)' : "{$state} pcs")
                    ->color(fn($state) => (int)$state === 0 ? 'warning' : null),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->state(function (OrderItem $record) {
                        $details = $record->size_and_request_details ?? [];
                        
                        if ($record->size === 'Custom') {
                            return $record->quantity * $record->price;
                        }

                        $itemsInGroup = $this->getItemsInGroup($record);

                        return $itemsInGroup->sum(fn($item) => $item->quantity * $item->price);
                      })
                    ->money('idr')
                    ->weight('bold')
                    ->color(fn($state) => (int)$state === 0 ? 'warning' : 'success'),
            ])
            ->headerActions([
                Action::make('edit_parent_product')
                    ->label('Edit Info Produk Utama')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->closeModalByClickingAway(false)
                    ->visible(fn() => in_array(auth()->user()->role, ['owner', 'admin']) && $this->order->orderItems()->exists())
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Simpan Perubahan Produk'))
                    ->modalHeading('Edit Informasi Produk Utama')
                    ->modalWidth('2xl')
                    ->form(function () use ($bahanOptions, $categoryOptions) {
                        return [
                            Select::make('select_product_name')
                                ->label('Pilih Produk yang Ingin Diedit')
                                ->options(fn() => OrderItem::where('order_id', $this->order->id)->pluck('product_name', 'product_name')->unique())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (!$state) return;
                                    $sample = OrderItem::where('order_id', $this->order->id)->where('product_name', $state)->first();
                                    if ($sample) {
                                        $details = $sample->size_and_request_details ?? [];
                                        $set('new_product_name', $sample->product_name);
                                        $set('new_category', $sample->production_category === 'custom' ? 'produksi' : ($sample->production_category ?? 'produksi'));
                                        $set('new_bahan_id', $sample->bahan_id);
                                        $set('new_material_variant_id', $details['material_variant_id'] ?? null);
                                        $set('new_sablon_teknik', $details['sablon_jenis'] ?? null);
                                        $set('new_sablon_lokasi', $details['sablon_lokasi'] ?? null);
                                        $set('new_sablon_keterangan', $details['sablon_keterangan'] ?? null);
                                    }
                                }),

                            Section::make('Informasi Produk & Bahan')
                                ->visible(fn(Get $get) => filled($get('select_product_name')))
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('new_product_name')
                                            ->label('Nama Produk / Item')
                                            ->required(),
                                        Select::make('new_category')
                                            ->label('Kategori')
                                            ->options($categoryOptions)
                                            ->required()
                                            ->live(),
                                    ]),
                                    Grid::make(2)->schema([
                                        Select::make('new_bahan_id')
                                            ->label('Bahan Utama')
                                            ->options($bahanOptions)
                                            ->searchable()
                                            ->live()
                                            ->visible(fn(Get $get) => $get('new_category') === 'produksi')
                                            ->afterStateUpdated(fn(Set $set) => $set('new_material_variant_id', null)),
                                        Select::make('new_material_variant_id')
                                            ->label('Warna / Varian Bahan')
                                            ->allowHtml()
                                            ->options(function(Get $get) {
                                                $bId = $get('new_bahan_id');
                                                if (!$bId) return [];
                                                return \App\Models\MaterialVariant::where('material_id', $bId)
                                                    ->get()
                                                    ->mapWithKeys(function($v) {
                                                        $label = $v->color_name . ($v->color_code ? " ({$v->color_code})" : "");
                                                        return [$v->id => $label];
                                                    });
                                            })
                                            ->searchable()
                                            ->visible(fn(Get $get) => $get('new_category') === 'produksi' && filled($get('new_bahan_id'))),
                                    ]),
                                ])->compact(),

                            Section::make('Desain Sablon / Bordir Utama')
                                ->visible(fn(Get $get) => filled($get('select_product_name')) && in_array($get('new_category'), ['produksi', 'custom']))
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('new_sablon_teknik')
                                            ->label('Teknik Sablon / Bordir')
                                            ->options(fn () => \App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                            ->searchable()
                                            ->placeholder('Tanpa Sablon/Bordir')
                                            ->nullable(),
                                        Select::make('new_sablon_lokasi')
                                            ->label('Titik Lokasi')
                                            ->options(fn () => \App\Models\PrintType::where('category', 'lokasi')->pluck('name', 'name'))
                                            ->searchable()
                                            ->placeholder('Pilih titik lokasi...')
                                            ->nullable(),
                                    ]),
                                    \Filament\Forms\Components\Textarea::make('new_sablon_keterangan')
                                        ->label('Keterangan Desain')
                                        ->placeholder('misal: Logo depan dada atau rincian posisi')
                                        ->rows(2)
                                        ->nullable(),
                                ])->compact(),
                        ];
                    })
                    ->action(function (array $data) {
                        $oldName = $data['select_product_name'] ?? null;
                        if (!$oldName) return;

                        $newName = $data['new_product_name'] ?? $oldName;
                        $newCat = $data['new_category'] ?? 'produksi';
                        $newBahan = ($newCat === 'produksi') ? ($data['new_bahan_id'] ?? null) : null;
                        $newMatVar = ($newCat === 'produksi') ? ($data['new_material_variant_id'] ?? null) : null;
                        $newTeknik = $data['new_sablon_teknik'] ?? null;
                        $newLokasi = $data['new_sablon_lokasi'] ?? null;
                        $newKet = $data['new_sablon_keterangan'] ?? null;

                        $itemsToUpdate = OrderItem::where('order_id', $this->order->id)
                            ->where('product_name', $oldName)
                            ->get();

                        foreach ($itemsToUpdate as $item) {
                            $details = $item->size_and_request_details ?? [];
                            $details['material_variant_id'] = $newMatVar;
                            $details['sablon_jenis'] = $newTeknik;
                            $details['sablon_lokasi'] = $newLokasi;
                            $details['sablon_keterangan'] = $newKet;

                            $item->update([
                                'product_name' => $newName,
                                'production_category' => $newCat,
                                'bahan_id' => $newBahan,
                                'size_and_request_details' => $details,
                            ]);
                        }

                        $this->refreshOrderData();
                        Notification::make()->success()->title("Informasi Produk Utama '{$oldName}' berhasil diperbarui ke seluruh kelompok!")->send();
                    }),

                Action::make('bulk_generate')
                    ->label('Bulk Generate / Tambah Produk')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->closeModalByClickingAway(false)
                    ->visible(fn() => in_array(auth()->user()->role, ['owner', 'admin']))
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Tambahkan ke Pesanan'))
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions, $genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions, $collarOptions, $modelOptions, $tenantId) {
                        $isExistingProduct = fn(Get $get) => filled($get('bulk_product_name')) && OrderItem::where('order_id', $this->order->id)->where('product_name', $get('bulk_product_name'))->exists();

                        return [
                            Section::make('Kategori & Dasar')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('bulk_category')
                                            ->label('Pilih Kategori')
                                            ->options($categoryOptions)
                                            ->default('produksi')
                                            ->required()
                                            ->live()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated(),
                                        TextInput::make('bulk_product_name')
                                            ->label('Nama Produk/Jasa')
                                            ->datalist(function () {
                                                return OrderItem::where('order_id', $this->order->id)
                                                    ->distinct()
                                                    ->pluck('product_name')
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->placeholder('Pilih atau ketik nama baru...')
                                            ->helperText(fn(Get $get) => $isExistingProduct($get) ? '🔒 Spesifikasi dikunci.' : null)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if (!$state) return;
                                                $existing = OrderItem::where('order_id', $this->order->id)
                                                    ->where('product_name', $state)
                                                    ->latest('id')
                                                    ->first();

                                                if ($existing) {
                                                    $details = $existing->size_and_request_details ?? [];
                                                    $category = $existing->production_category === 'custom' ? 'produksi' : ($existing->production_category ?? 'produksi');
                                                    $set('bulk_category', $category);

                                                    // Cari harga standar (yang bukan ukuran Custom)
                                                    $stdItem = OrderItem::where('order_id', $this->order->id)
                                                        ->where('product_name', $state)
                                                        ->where('size', '!=', 'Custom')
                                                        ->first();
                                                    
                                                    // Cari harga custom
                                                    $customItem = OrderItem::where('order_id', $this->order->id)
                                                        ->where('product_name', $state)
                                                        ->where('size', 'Custom')
                                                        ->first();

                                                    if ($category === 'non_produksi') {
                                                        $variantId = $details['product_variant_id'] ?? null;
                                                        $productId = $details['supplier_product'] ?? null;
                                                        if (!$productId && $variantId) {
                                                            $productId = \App\Models\ProductVariant::find($variantId)?->product_id;
                                                        }

                                                        $set('bulk_product_id', (string) $productId);
                                                        $set('bulk_price_non', $stdItem?->price ?? $existing->price);
                                                        
                                                        if ($variantId) {
                                                            $colorName = \App\Models\ProductVariant::find($variantId)?->color_name;
                                                            $set('bulk_product_color', $colorName);
                                                        }
                                                    } elseif ($category === 'jasa') {
                                                        $set('bulk_price_jasa', $existing->price);
                                                        $set('bulk_qty_jasa', $existing->quantity);
                                                    } else {
                                                        // Restore Produksi specs
                                                        $set('bulk_price', $stdItem?->price ?? $existing->price);
                                                        $set('bulk_price_custom', $customItem?->price ?? null);
                                                        
                                                        $set('bulk_bahan', $existing->bahan_id);
                                                        $set('bulk_material_variant_id', $details['material_variant_id'] ?? null);

                                                        $set('specification_groups', [
                                                            [
                                                                'bulk_gender' => $details['gender'] ?? 'L',
                                                                'bulk_model' => $details['model'] ?? 'biasa',
                                                                'bulk_sleeve' => $details['sleeve_model'] ?? 'pendek',
                                                                'bulk_pocket' => $details['pocket_model'] ?? 'tanpa_saku',
                                                                'bulk_button' => $details['button_model'] ?? 'biasa',
                                                                'bulk_collar' => $details['collar_model'] ?? 'kemeja',
                                                                'enable_custom' => false,
                                                            ]
                                                        ]);
                                                        $set('bulk_sablon_teknik', $details['sablon_jenis'] ?? null);
                                                        $set('bulk_sablon_lokasi', $details['sablon_lokasi'] ?? null);
                                                        $set('bulk_sablon_keterangan', $details['sablon_keterangan'] ?? null);
                                                    }

                                                    Notification::make()->title("Spesifikasi '{$state}' disalin!")->success()->send();
                                                }
                                            })
                                            ->columnSpan(2),
                                    ]),
                                ])->compact(),
                            
                            Section::make('Detail Produksi')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('bulk_bahan')
                                            ->label('Bahan')
                                            ->options($bahanOptions)
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->afterStateUpdated(fn(Set $set) => $set('bulk_material_variant_id', null)),
                                        Select::make('bulk_material_variant_id')
                                            ->label('Warna / Varian')
                                            ->allowHtml()
                                            ->options(function (Get $get) {
                                                $bahanId = $get('bulk_bahan');
                                                if (!$bahanId) return [];
                                                return \App\Models\MaterialVariant::where('material_id', $bahanId)
                                                    ->get()
                                                    ->mapWithKeys(function($v) {
                                                        $label = $v->color_name . ($v->color_code ? " ({$v->color_code})" : "");
                                                        return [$v->id => $label];
                                                    });
                                            })
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->visible(fn(Get $get) => filled($get('bulk_bahan'))),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('bulk_stock_qty')
                                            ->label('Jml Bahan Terpakai')
                                            ->numeric()
                                            ->integer()
                                            ->placeholder('0')
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($get('bulk_category') !== 'produksi') return;
                                                $variantId = $get('bulk_material_variant_id');
                                                if (!$variantId) return;
                                                
                                                $v = \App\Models\MaterialVariant::find($variantId);
                                                // current_stock sudah mencerminkan sisa stok setelah dipotong saat item dibuat
                                                // jadi tidak perlu kurangi lagi dengan usedInOrder (akan double-deduction)
                                                $available = (int) ($v?->current_stock ?? 0);

                                                if ((int) $state > $available) {
                                                    $set('bulk_stock_qty', $available);
                                                    Notification::make()
                                                        ->title('Jumlah melebihi stok tersedia!')
                                                        ->body("Sisa stok yang bisa digunakan adalah {$available} " . ($v?->material?->unit ?? 'm'))
                                                        ->warning()
                                                        ->send();
                                                }
                                            })
                                            ->helperText(function(Get $get) {
                                                if ($get('bulk_category') !== 'produksi') return "";
                                                $variantId = $get('bulk_material_variant_id');
                                                $v = \App\Models\MaterialVariant::with('material')->find($variantId);
                                                if (!$v) return "";
                                                
                                                // current_stock sudah nilai sesungguhnya setelah pemotongan order sebelumnya
                                                $available = (int) ($v->current_stock ?? 0);
                                                $usedInOrder = (int) OrderItem::where('order_id', $this->order->id)
                                                    ->get()
                                                    ->filter(fn($item) => ($item->size_and_request_details['material_variant_id'] ?? null) == $variantId)
                                                    ->sum(fn($item) => (int) ($item->size_and_request_details['stock_qty_used'] ?? 0));
                                                
                                                return "Stok Tersedia: {$available} {$v->material->unit}" . ($usedInOrder > 0 ? " (Terpakai {$usedInOrder} di pesanan ini)" : "");
                                            })
                                            ->visible(function(Get $get) {
                                                if ($get('bulk_category') !== 'produksi') return false;
                                                $variantId = $get('bulk_material_variant_id');
                                                if (!$variantId) return false;
                                                $dbStock = (float) (\App\Models\MaterialVariant::find($variantId)?->current_stock ?? 0);
                                                return $dbStock > 0;
                                            }),
                                    ]),
                                ])->visible(fn(Get $get) => $get('bulk_category') === 'produksi')->compact(),

                            Section::make('Detail Non-Produksi')
                                ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('bulk_product_id')
                                            ->label('Pilih Produk Katalog')
                                            ->options(Product::where('shop_id', $tenantId)->pluck('name', 'id'))
                                            ->searchable()->live()
                                            ->required()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),
                                        Select::make('bulk_product_color')
                                            ->label('Pilih Warna')
                                            ->options(function (Get $get) {
                                                $productId = $get('bulk_product_id');
                                                if (!$productId) return [];
                                                return \App\Models\ProductVariant::where('product_id', $productId)
                                                    ->whereNotNull('color_name')
                                                    ->get()
                                                    ->mapWithKeys(fn($v) => [
                                                        $v->color_name => $v->color_code ? "{$v->color_name} ({$v->color_code})" : $v->color_name
                                                    ]);
                                            })
                                            ->searchable()
                                            ->live()
                                            ->required()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),
                                    ]),
                                ])->compact(),

                            Section::make('Detail Sablon / Bordir')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('bulk_sablon_teknik')
                                            ->label('Teknik Sablon / Bordir')
                                            ->options(fn () => \App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                            ->default(fn () => \App\Models\PrintType::where('category', 'jenis')->first()?->name)
                                            ->searchable()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Nama Teknik')
                                                    ->required()
                                            ])
                                            ->createOptionUsing(function (array $data) use ($tenantId) {
                                                $newRecord = \App\Models\PrintType::create([
                                                    'shop_id' => $tenantId,
                                                    'category' => 'jenis',
                                                    'name' => $data['name'],
                                                    'is_active' => true,
                                                ]);
                                                return $newRecord->name;
                                            })
                                            ->required(fn(Get $get) => $get('bulk_category') === 'jasa'),
                                        Select::make('bulk_sablon_lokasi')
                                            ->label('Titik Lokasi')
                                            ->options(fn () => \App\Models\PrintType::where('category', 'lokasi')->pluck('name', 'name'))
                                            ->default(fn () => \App\Models\PrintType::where('category', 'lokasi')->first()?->name)
                                            ->searchable()
                                            ->disabled($isExistingProduct)
                                            ->dehydrated()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Nama Lokasi')
                                                    ->required()
                                            ])
                                            ->createOptionUsing(function (array $data) use ($tenantId) {
                                                $newRecord = \App\Models\PrintType::create([
                                                    'shop_id' => $tenantId,
                                                    'category' => 'lokasi',
                                                    'name' => $data['name'],
                                                    'is_active' => true,
                                                ]);
                                                return $newRecord->name;
                                            })
                                            ->required(fn(Get $get) => $get('bulk_category') === 'jasa'),
                                    ]),
                                    \Filament\Forms\Components\Textarea::make('bulk_sablon_keterangan')
                                        ->label('Keterangan')
                                        ->placeholder('misal: Logo depan dada atau rincian posisi')
                                        ->disabled($isExistingProduct)
                                        ->dehydrated()
                                        ->rows(2),
                                ])->compact(),

                            Section::make('Harga & Jumlah')
                                ->visible(fn(Get $get) => $get('bulk_category') === 'jasa')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('bulk_price_jasa')
                                            ->label('Harga Jasa')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->placeholder('0')
                                            ->required(),
                                        TextInput::make('bulk_qty_jasa')
                                            ->label('Jumlah (Qty)')
                                            ->numeric()
                                            ->default(1)
                                            ->required(),
                                    ]),
                                ])->compact(),

                            Section::make('Jumlah & Harga per Ukuran')
                                ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi')
                                ->collapsible()
                                ->collapsed(true)
                                ->schema([
                                    Grid::make(12)->schema(function (Get $get) use ($sizeOptions) {
                                        $productId = $get('bulk_product_id');
                                        $colorName = $get('bulk_product_color');

                                        $headers = [
                                            Placeholder::make('hdr_size_non')
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Ukuran</div>'))
                                                ->columnSpan(2),
                                            Placeholder::make('hdr_qty_non')
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Jumlah (Pcs) <span style="color: #ef4444;">*</span></div>'))
                                                ->columnSpan(4),
                                            Placeholder::make('hdr_price_non')
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Harga Satuan <span style="color: #ef4444;">*</span></div>'))
                                                ->columnSpan(6),
                                        ];

                                        $items = [];
                                        if ($productId && $colorName) {
                                            $variants = \App\Models\ProductVariant::where('product_id', $productId)
                                                ->where('color_name', $colorName)
                                                ->get()
                                                ->keyBy('size');

                                            foreach ($sizeOptions as $key => $label) {
                                                $variant = $variants->get($key);
                                                if (!$variant) continue;

                                                $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                                    ->where('production_category', 'non_produksi')
                                                    ->get()
                                                    ->filter(fn($item) => ($item->size_and_request_details['product_variant_id'] ?? null) == $variant->id)
                                                    ->sum(fn($item) => (float) ($item->size_and_request_details['stock_qty_used'] ?? 0));
                                                
                                                $available = round(max(0, $variant->stock - $usedInOrder), 3);
                                                $stockText = "Tersedia: {$available} pcs" . ($usedInOrder > 0 ? " (Terpakai {$usedInOrder})" : "");

                                                $items[] = [
                                                    'key' => $key,
                                                    'label' => $label,
                                                    'stockText' => $stockText,
                                                ];
                                            }
                                        } else {
                                            foreach ($sizeOptions as $key => $label) {
                                                $items[] = [
                                                    'key' => $key,
                                                    'label' => $label,
                                                ];
                                            }
                                        }

                                        $rows = [];
                                        foreach ($items as $item) {
                                            $label = $item['label'];
                                            $key = $item['key'];
                                            $stockText = $item['stockText'] ?? null;

                                            $qtyField = TextInput::make("non_qty_{$key}")
                                                ->hiddenLabel()
                                                ->numeric()
                                                ->placeholder('0')
                                                ->extraInputAttributes(['onclick' => 'this.select()'])
                                                ->columnSpan(4);

                                            if ($stockText) {
                                                $qtyField->helperText($stockText);
                                            }

                                            $rows = array_merge($rows, [
                                                Placeholder::make("non_lbl_{$key}")
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString("<div style='font-size: 1rem; font-weight: 700; display: flex; align-items: center; min-height: 36px; color: #374151;'>{$label}</div>"))
                                                    ->columnSpan(2),
                                                $qtyField,
                                                TextInput::make("non_price_{$key}")
                                                    ->hiddenLabel()
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->placeholder('0')
                                                    ->extraInputAttributes(['onclick' => 'this.select()'])
                                                    ->columnSpan(6),
                                            ]);
                                        }

                                        return array_merge($headers, $rows);
                                    })
                                    ->extraAttributes(['style' => 'column-gap: 1rem; row-gap: 0.25rem;']),
                                ])->compact(),

                            \Filament\Forms\Components\Repeater::make('specification_groups')
                                ->label('Daftar Spesifikasi & Ukuran')
                                ->itemLabel(fn(array $state): string => !empty($state['group_label']) ? $state['group_label'] : (($state['bulk_gender'] ?? 'L') === 'L' ? 'Laki-laki' : 'Perempuan'))
                                ->default([
                                    [
                                        'group_label' => 'Laki-laki',
                                        'bulk_gender' => 'L',
                                        'bulk_model' => 'biasa',
                                        'bulk_sleeve' => 'pendek',
                                        'bulk_pocket' => 'tanpa_saku',
                                        'bulk_button' => 'biasa',
                                        'bulk_collar' => 'kemeja',
                                        'enable_custom' => false,
                                    ]
                                ])
                                ->schema([
                                    TextInput::make('group_label')
                                        ->label('Nama Pengelompokan / Label Spesifikasi')
                                        ->placeholder('Misal: Seragam Pria, Atasan Wanita Tunik, Model Lengan Panjang, dsb.')
                                        ->live(onBlur: true)
                                        ->columnSpanFull(),
                                    Grid::make(3)->schema([
                                        Select::make('bulk_gender')
                                            ->label('Gender')
                                            ->options($genderOptions)
                                            ->default('L')
                                            ->live(),
                                        Select::make('bulk_model')
                                            ->label('Model')
                                            ->options($modelOptions)
                                            ->default('biasa')
                                            ->visible(fn(Get $get) => $get('bulk_gender') === 'P')
                                            ->required(fn(Get $get) => $get('bulk_gender') === 'P'),
                                        Select::make('bulk_sleeve')
                                            ->label('Lengan')
                                            ->options($sleeveOptions)
                                            ->default('pendek'),
                                        Select::make('bulk_pocket')
                                            ->label('Saku')
                                            ->options($pocketOptions)
                                            ->default('tanpa_saku'),
                                        Select::make('bulk_button')
                                            ->label('Kancing')
                                            ->options($buttonOptions)
                                            ->default('biasa'),
                                        Select::make('bulk_collar')
                                            ->label('Kerah')
                                            ->options($collarOptions)
                                            ->default('kemeja'),
                                        Textarea::make('spec_notes')
                                            ->label('Catatan Khusus Spesifikasi Ini (Notes)')
                                            ->placeholder('Contoh: Pakai furing dalam, saku bagian dalam, karet pinggang, dll.')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])->visible(fn(Get $get) => in_array($get('bulk_category') ?? 'produksi', ['produksi', 'custom'])),
                                    
                                    Section::make(new \Illuminate\Support\HtmlString('<span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Jumlah & Harga per Ukuran (Size Toko)</span>'))
                                        ->collapsible()
                                        ->collapsed()
                                        ->schema([
                                            Grid::make(12)->schema(function (Get $get) use ($sizeOptions) {
                                                $category = $get('bulk_category');
                                                $productId = $get('bulk_product_id');
                                                $colorName = $get('bulk_product_color');

                                                $headers = [
                                                    Placeholder::make('hdr_size')
                                                        ->hiddenLabel()
                                                        ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Ukuran</div>'))
                                                        ->columnSpan(2),
                                                    Placeholder::make('hdr_qty')
                                                        ->hiddenLabel()
                                                        ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Jumlah (Pcs) <span style="color: #ef4444;">*</span></div>'))
                                                        ->columnSpan(4),
                                                    Placeholder::make('hdr_price')
                                                        ->hiddenLabel()
                                                        ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 4px;">Harga Satuan <span style="color: #ef4444;">*</span></div>'))
                                                        ->columnSpan(6),
                                                ];

                                                $items = [];
                                                if ($category === 'non_produksi' && $productId && $colorName) {
                                                    $variants = \App\Models\ProductVariant::where('product_id', $productId)
                                                        ->where('color_name', $colorName)
                                                        ->get()
                                                        ->keyBy('size');

                                                    foreach ($sizeOptions as $key => $label) {
                                                        $variant = $variants->get($key);
                                                        if (!$variant) continue;

                                                        $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                                            ->where('production_category', 'non_produksi')
                                                            ->get()
                                                            ->filter(fn($item) => ($item->size_and_request_details['product_variant_id'] ?? null) == $variant->id)
                                                            ->count();
                                                        
                                                        $available = round(max(0, $variant->stock - $usedInOrder), 3);
                                                        $stockText = "Tersedia: {$available} pcs" . ($usedInOrder > 0 ? " (Terpakai {$usedInOrder})" : "");

                                                        $items[] = [
                                                            'key' => $key,
                                                            'label' => $label,
                                                            'stockText' => $stockText,
                                                        ];
                                                    }
                                                } else {
                                                    foreach ($sizeOptions as $key => $label) {
                                                        $items[] = [
                                                            'key' => $key,
                                                            'label' => $label,
                                                        ];
                                                    }
                                                }

                                                $rows = [];
                                                foreach ($items as $item) {
                                                    $label = $item['label'];
                                                    $key = $item['key'];
                                                    $stockText = $item['stockText'] ?? null;

                                                    $qtyField = TextInput::make("qty_{$key}")
                                                        ->hiddenLabel()
                                                        ->numeric()
                                                        ->placeholder('0')
                                                        ->extraInputAttributes(['onclick' => 'this.select()'])
                                                        ->columnSpan(4);

                                                    if ($stockText) {
                                                        $qtyField->helperText($stockText);
                                                    }

                                                    $rows = array_merge($rows, [
                                                        Placeholder::make("lbl_{$key}")
                                                            ->hiddenLabel()
                                                            ->content(new \Illuminate\Support\HtmlString("<div style='font-size: 1rem; font-weight: 700; display: flex; align-items: center; min-height: 36px; color: #374151;'>{$label}</div>"))
                                                            ->columnSpan(2),
                                                        $qtyField,
                                                        TextInput::make("price_{$key}")
                                                            ->hiddenLabel()
                                                            ->numeric()
                                                            ->prefix('Rp')
                                                            ->placeholder(fn(Get $get) => ($category === 'non_produksi' ? $get('../../bulk_price_non') : $get('../../bulk_price')) ?: '0')
                                                            ->extraInputAttributes(['onclick' => 'this.select()'])
                                                            ->columnSpan(6),
                                                    ]);
                                                }

                                                return array_merge($headers, $rows);
                                            })
                                            ->extraAttributes(['style' => 'column-gap: 1rem; row-gap: 0.25rem;']),
                                        ])->compact(),
                                    Section::make(new \Illuminate\Support\HtmlString('<span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Rincian Ukuran Badan (Custom / Per-Pcs)</span>'))
                                        ->collapsible()
                                        ->collapsed()
                                        ->schema([
                                            TextInput::make('price_custom')
                                                ->label(new \Illuminate\Support\HtmlString('Harga Satuan Custom (Default)'))
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->columnSpanFull()
                                                ->placeholder(fn(Get $get) => $get('../../bulk_price_custom') ?: ($get('../../bulk_price') ?: '0'))
                                                ->extraInputAttributes(['onclick' => 'this.select()']),
                                            \Filament\Forms\Components\Repeater::make('custom_people')
                                                ->label('Pilih / Tambah Baju Custom')
                                                ->itemLabel(fn(array $state) => (!empty($state['name']) ? $state['name'] : 'Baju Custom') . (!empty($state['ld']) ? " (LD: {$state['ld']} cm)" : ''))
                                                ->addActionLabel('+ Tambah Baju Custom')
                                                ->defaultItems(0)
                                                ->collapsible()
                                                ->compact()
                                                ->schema([
                                                    Grid::make(12)->schema([
                                                        TextInput::make('name')
                                                            ->label('Nama Pemesan / Label')
                                                            ->placeholder('Misal: Budi / Direktur')
                                                            ->live(onBlur: true)
                                                            ->columnSpan(7),
                                                        TextInput::make('notes')
                                                            ->label('Catatan Khusus Pcs Ini')
                                                            ->placeholder('Misal: Lengan longgar, dsb.')
                                                            ->columnSpan(5),
                                                    ]),
                                                    Grid::make(3)->schema([
                                                        TextInput::make('ld')->label('LD (Lingkar Dada)')->placeholder('108')->numeric()->suffix('cm')->live(onBlur: true),
                                                        TextInput::make('pb')->label('PB (Panjang Badan)')->placeholder('72')->numeric()->suffix('cm'),
                                                        TextInput::make('pl')->label('PL (Panjang Lengan)')->placeholder('24')->numeric()->suffix('cm'),
                                                    ]),
                                                    Grid::make(3)->schema([
                                                        TextInput::make('lb')->label('LB (Lebar Bahu)')->placeholder('44')->numeric()->suffix('cm'),
                                                        TextInput::make('lp')->label('LP (Lingkar Pinggang)')->placeholder('86')->numeric()->suffix('cm'),
                                                        TextInput::make('lph')->label('LPh (Lingkar Paha)')->placeholder('58')->numeric()->suffix('cm'),
                                                    ]),
                                                ]),
                                        ])->compact(),
                                ])
                                ->visible(fn(Get $get) => in_array($get('bulk_category') ?? 'produksi', ['produksi', 'custom']))
                                ->collapsible()
                                ->collapsed(true)
                                ->extraAttributes(['class' => 'border border-gray-300 dark:border-gray-700 rounded-xl p-4 bg-gray-50/50 dark:bg-gray-900/30']),
                        ];
                    })
                    ->modalWidth('4xl')
                    ->action(function (array $data) use ($sizeOptions) {
                        $category = $data['bulk_category'];
                        $productName = $data['bulk_product_name'] ?? null;

                        if ($productName) {
                            OrderItem::where('order_id', $this->order->id)
                                ->get()
                                ->filter(fn($item) => ($item->size_and_request_details['is_draft'] ?? false) === true && $item->product_name === $productName)
                                ->each->delete();
                        }
                        


                        $productName = $data['bulk_product_name'];
                        if ($category === 'jasa') {
                            $jasaQty = (int) ($data['bulk_qty_jasa'] ?? 1);
                            $this->order->orderItems()->create([
                                'product_name' => $productName,
                                'production_category' => 'jasa',
                                'price' => (int) ($data['bulk_price_jasa'] ?? 0),
                                'quantity' => $jasaQty,
                                'design_status' => $this->getNewItemDesignStatus($productName),
                                'size_and_request_details' => [
                                    'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                    'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                    'sablon_keterangan' => $data['bulk_sablon_keterangan'] ?? null,
                                ],
                            ]);
                        } else if ($category === 'non_produksi') {
                            $productId = $data['bulk_product_id'] ?? null;
                            $colorName = $data['bulk_product_color'] ?? null;
                            $variants = \App\Models\ProductVariant::where('product_id', $productId)
                                ->where('color_name', $colorName)
                                ->get()
                                ->keyBy('size');

                            foreach ($sizeOptions as $key => $label) {
                                $qty = (int) ($data["non_qty_{$key}"] ?? 0);
                                if ($qty <= 0) continue;

                                $v = $variants->get($key);
                                $variantId = $v?->id;
                                $availableStock = (float) ($v?->stock ?? 0);
                                
                                $alreadyInOrder = OrderItem::where('order_id', $this->order->id)
                                    ->get()
                                    ->filter(fn($item) => ($item->size_and_request_details['product_variant_id'] ?? null) == $variantId)
                                    ->sum(fn($item) => (float) ($item->size_and_request_details['stock_qty_used'] ?? 0));
                                
                                $currentRemaining = max(0, $availableStock - $alreadyInOrder);
                                $stockToUse = min($qty, (int) $currentRemaining);

                                $this->order->orderItems()->create([
                                    'product_name' => $productName,
                                    'production_category' => $category,
                                    'bahan_id' => null,
                                    'size' => $key,
                                    'price' => (int) ($data["non_price_{$key}"] ?? 0),
                                    'quantity' => $qty,
                                    'design_status' => $this->getNewItemDesignStatus($productName),
                                    'size_and_request_details' => [
                                        'gender' => null,
                                        'sleeve_model' => null,
                                        'pocket_model' => null,
                                        'button_model' => null,
                                        'collar_model' => null,
                                        'is_tunic' => false,
                                        'model' => null,
                                        'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                        'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                        'sablon_keterangan' => $data['bulk_sablon_keterangan'] ?? null,
                                        'material_variant_id' => null,
                                        'product_variant_id' => $variantId,
                                        'supplier_product' => $productId,
                                        'use_stock' => ($stockToUse > 0),
                                        'stock_qty_used' => $stockToUse,
                                    ],
                                ]);
                            }
                        } else {
                            $specGroups = $data['specification_groups'] ?? [];
                            if (empty($specGroups)) return;

                            // Inisialisasi di LUAR loop agar total stok terpakai hanya diletakkan
                            // di item PERTAMA yang berhasil dibuat dari seluruh batch bulk generate ini
                            $variantId = null;
                            $useStock = false;
                            $isFirstItem = true;
                            $remainingStockToStore = 0;
                            if ($category === 'produksi') {
                                $variantId = $data['bulk_material_variant_id'] ?? null;
                                $useStock = filled($data['bulk_stock_qty'] ?? null);
                                $remainingStockToStore = $useStock ? (int) ($data['bulk_stock_qty']) : 0;
                            }

                            foreach ($specGroups as $group) {
                                $groupCreated = false;
                                foreach ($sizeOptions as $key => $label) {
                                    $qty = (int) ($group["qty_{$key}"] ?? 0);
                                    if ($qty <= 0) continue;

                                    $groupCreated = true;
                                    if ($category === 'produksi') {
                                        $itemStockUsed = ($isFirstItem && $useStock) ? $remainingStockToStore : 0;
                                        $isFirstItem = false;
                                        $remainingStockToStore = 0;

                                        $this->order->orderItems()->create([
                                            'product_name' => $productName,
                                            'production_category' => $category,
                                            'bahan_id' => $data['bulk_bahan'] ?? null,
                                            'size' => $key,
                                            'price' => (int) ($group["price_{$key}"] ?? $data['bulk_price'] ?? 0),
                                            'quantity' => $qty,
                                            'design_status' => $this->getNewItemDesignStatus($productName),
                                            'size_and_request_details' => [
                                                'gender' => $group['bulk_gender'] ?? 'L',
                                                'sleeve_model' => $group['bulk_sleeve'] ?? 'pendek',
                                                'pocket_model' => $group['bulk_pocket'] ?? 'tanpa_saku',
                                                'button_model' => $group['bulk_button'] ?? 'biasa',
                                                'collar_model' => $group['bulk_collar'] ?? 'biasa',
                                                'is_tunic' => ($group['bulk_model'] ?? 'biasa') === 'tunic',
                                                'model' => $group['bulk_model'] ?? 'biasa',
                                                'group_label' => $group['group_label'] ?? null,
                                                'spec_notes' => $group['spec_notes'] ?? null,
                                                'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                                'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                                'sablon_keterangan' => $data['bulk_sablon_keterangan'] ?? null,
                                                'material_variant_id' => $variantId,
                                                'product_variant_id' => null,
                                                'supplier_product' => null,
                                                'use_stock' => $useStock,
                                                'stock_qty_used' => $itemStockUsed,
                                            ],
                                        ]);
                                    }
                                }
                                
                                $customPeople = $group['custom_people'] ?? [];
                                $cQty = count($customPeople);
                                if ($cQty > 0) {
                                    $groupCreated = true;
                                    $customVariantId = ($category === 'produksi') ? ($data['bulk_material_variant_id'] ?? null) : null;
                                    $customUseStock = ($category === 'produksi' && filled($data['bulk_stock_qty'] ?? null));

                                    for ($i = 0; $i < $cQty; $i++) {
                                        $personData = $customPeople[$i] ?? [];
                                        $customStockUsed = ($isFirstItem && $customUseStock) ? $remainingStockToStore : 0;
                                        $isFirstItem = false;
                                        $remainingStockToStore = 0;

                                        $personPrice = (int) ($group['price_custom'] ?? $data['bulk_price_custom'] ?? $data['bulk_price'] ?? 0);
                                        $personName = !empty($personData['name']) ? $personData['name'] : null;

                                        $this->order->orderItems()->create([
                                            'product_name' => $productName,
                                            'production_category' => $category,
                                            'bahan_id' => ($category === 'produksi') ? ($data['bulk_bahan'] ?? null) : null,
                                            'size' => 'Custom',
                                            'quantity' => 1,
                                            'price' => $personPrice,
                                            'recipient_name' => $personName,
                                            'design_status' => $this->getNewItemDesignStatus($productName),
                                            'size_and_request_details' => [
                                                'gender' => $group['bulk_gender'] ?? 'L',
                                                'sleeve_model' => $group['bulk_sleeve'] ?? 'pendek',
                                                'pocket_model' => $group['bulk_pocket'] ?? 'tanpa_saku',
                                                'button_model' => $group['bulk_button'] ?? 'biasa',
                                                'collar_model' => $group['bulk_collar'] ?? 'biasa',
                                                'is_tunic' => ($group['bulk_model'] ?? 'biasa') === 'tunic',
                                                'model' => $group['bulk_model'] ?? 'biasa',
                                                'group_label' => $group['group_label'] ?? null,
                                                'spec_notes' => $group['spec_notes'] ?? null,
                                                'person_notes' => $personData['notes'] ?? null,
                                                'custom_measurements' => [
                                                    'ld' => $personData['ld'] ?? null,
                                                    'pb' => $personData['pb'] ?? null,
                                                    'pl' => $personData['pl'] ?? null,
                                                    'lb' => $personData['lb'] ?? null,
                                                    'lp' => $personData['lp'] ?? null,
                                                    'lph' => $personData['lph'] ?? null,
                                                ],
                                                'measurements' => [
                                                    'LD' => $personData['ld'] ?? null,
                                                    'PB' => $personData['pb'] ?? null,
                                                    'PL' => $personData['pl'] ?? null,
                                                    'LB' => $personData['lb'] ?? null,
                                                    'LP' => $personData['lp'] ?? null,
                                                    'LPh' => $personData['lph'] ?? null,
                                                ],
                                                'LD' => $personData['ld'] ?? null,
                                                'PB' => $personData['pb'] ?? null,
                                                'PL' => $personData['pl'] ?? null,
                                                'LB' => $personData['lb'] ?? null,
                                                'LP' => $personData['lp'] ?? null,
                                                'LPh' => $personData['lph'] ?? null,
                                                'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                                'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                                'sablon_keterangan' => $data['bulk_sablon_keterangan'] ?? null,
                                                'material_variant_id' => $customVariantId,
                                                'product_variant_id' => null,
                                                'supplier_product' => null,
                                                'use_stock' => $customUseStock,
                                                'stock_qty_used' => $customStockUsed,
                                            ],
                                        ]);
                                    }
                                }

                                if (!$groupCreated) {
                                    $this->order->orderItems()->create([
                                        'product_name' => $productName,
                                        'production_category' => $category,
                                        'bahan_id' => ($category === 'produksi') ? ($data['bulk_bahan'] ?? null) : null,
                                        'size' => 'M',
                                        'quantity' => 0,
                                        'price' => (int) ($group['price_custom'] ?? $data['bulk_price'] ?? 0),
                                        'design_status' => $this->getNewItemDesignStatus($productName),
                                        'size_and_request_details' => [
                                            'gender' => $group['bulk_gender'] ?? 'L',
                                            'sleeve_model' => $group['bulk_sleeve'] ?? 'pendek',
                                            'pocket_model' => $group['bulk_pocket'] ?? 'tanpa_saku',
                                            'button_model' => $group['bulk_button'] ?? 'biasa',
                                            'collar_model' => $group['bulk_collar'] ?? 'biasa',
                                            'is_tunic' => ($group['bulk_model'] ?? 'biasa') === 'tunic',
                                            'model' => $group['bulk_model'] ?? 'biasa',
                                            'group_label' => $group['group_label'] ?? null,
                                            'spec_notes' => $group['spec_notes'] ?? null,
                                            'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                            'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                            'sablon_keterangan' => $data['bulk_sablon_keterangan'] ?? null,
                                            'material_variant_id' => $variantId,
                                            'product_variant_id' => null,
                                            'supplier_product' => null,
                                            'use_stock' => $useStock,
                                            'stock_qty_used' => 0,
                                        ],
                                    ]);
                                }
                            }
                        }
                        $this->bulkFormDraft = null;
                        session()->forget("bulk_draft_{$this->order->id}");
                        $this->refreshOrderData();
                    }),
                Action::make('configure_spec_options')
                    ->label('Atur Opsi Spesifikasi')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->visible(fn() => in_array(auth()->user()->role, ['owner', 'admin']))
                    ->modalHeading('Atur Pilihan Spesifikasi Produk')
                    ->modalDescription('Ubah pilihan Lengan, Saku, Kancing, dan Kerah yang dapat dipilih saat input pesanan.')
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Simpan Perubahan'))
                    ->modalWidth('lg')
                    ->form(function() use ($tenantId) {
                        $shop = \App\Models\Shop::withoutGlobalScope(\App\Models\Scopes\ShopScope::class)->find($tenantId);
                        $savedSpecs = $shop?->production_spec_options ?? [];

                        return [
                            \Filament\Forms\Components\TagsInput::make('sleeve_options')
                                ->label('Pilihan Lengan')
                                ->placeholder('Tambah opsi lengan...')
                                ->helperText('Ketik lalu tekan Enter/Tab untuk menambah')
                                ->splitKeys(['Tab', ','])
                                ->default($savedSpecs['sleeve_options'] ?? ['Pendek', 'Panjang', '3/4']),
                            \Filament\Forms\Components\TagsInput::make('pocket_options')
                                ->label('Pilihan Saku')
                                ->placeholder('Tambah opsi saku...')
                                ->helperText('Ketik lalu tekan Enter/Tab untuk menambah')
                                ->splitKeys(['Tab', ','])
                                ->default($savedSpecs['pocket_options'] ?? ['Tanpa Saku', 'Jalan Tol', 'Semi Kelewang', 'Kelewang', 'Saku Tabuk', 'Patah Suduik']),
                            \Filament\Forms\Components\TagsInput::make('button_options')
                                ->label('Pilihan Kancing')
                                ->placeholder('Tambah opsi kancing...')
                                ->helperText('Ketik lalu tekan Enter/Tab untuk menambah')
                                ->splitKeys(['Tab', ','])
                                ->default($savedSpecs['button_options'] ?? ['Biasa', 'Tutup']),
                            \Filament\Forms\Components\TagsInput::make('collar_options')
                                ->label('Pilihan Kerah')
                                ->placeholder('Tambah opsi kerah...')
                                ->helperText('Ketik lalu tekan Enter/Tab untuk menambah')
                                ->splitKeys(['Tab', ','])
                                ->default($savedSpecs['collar_options'] ?? ['Shanghai', 'Kemeja', 'Ramora']),
                        ];
                    })
                    ->action(function (array $data) use ($tenantId) {
                        $shop = \App\Models\Shop::withoutGlobalScope(\App\Models\Scopes\ShopScope::class)->find($tenantId);
                        if ($shop) {
                            $shop->update([
                                'production_spec_options' => [
                                    'sleeve_options' => $data['sleeve_options'],
                                    'pocket_options' => $data['pocket_options'],
                                    'button_options' => $data['button_options'],
                                    'collar_options' => $data['collar_options'],
                                ],
                            ]);
                            Notification::make()->success()->title('Pilihan spesifikasi berhasil diperbarui!')->send();
                        }
                    }),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    Action::make('edit_variant')
                    ->label('Edit Item')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->visible(fn (OrderItem $record) => in_array(auth()->user()->role, ['owner', 'admin']) && $record->canBeEdited())
                    ->modalHeading(fn (OrderItem $record) => "Edit Item: " . $record->product_name)
                    ->modalSubmitActionLabel('Simpan Perubahan')
                    ->modalWidth('4xl')
                    ->form([
                        Section::make('Spesifikasi & Desain')
                            ->visible(fn(OrderItem $record) => in_array($record->production_category, ['produksi', 'custom']))
                            ->schema([
                                TextInput::make('edit_group_label')
                                    ->label('Nama Pengelompokan / Label Spesifikasi')
                                    ->placeholder('Misal: Seragam Pria, Atasan Wanita Tunik, Model Lengan Panjang, dsb.')
                                    ->columnSpanFull(),
                                Grid::make(3)->schema([
                                    Select::make('edit_gender')
                                        ->label('Gender / JK')
                                        ->options($genderOptions)
                                        ->live()
                                        ->required(),
                                    Select::make('edit_sleeve')
                                        ->label('Lengan')
                                        ->options($sleeveOptions)
                                        ->required(),
                                    Select::make('edit_pocket')
                                        ->label('Saku')
                                        ->options($pocketOptions)
                                        ->required(),
                                ]),
                                Grid::make(3)->schema([
                                    Select::make('edit_button')
                                        ->label('Kancing')
                                        ->options($buttonOptions)
                                        ->required(),
                                    Select::make('edit_collar')
                                        ->label('Kerah')
                                        ->options($collarOptions)
                                        ->required(),
                                    Select::make('edit_model')
                                        ->label('Model')
                                        ->options($modelOptions)
                                        ->required(),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('edit_sablon_teknik')
                                        ->label('Teknik Sablon / Bordir')
                                        ->options(fn () => \App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                        ->searchable()
                                        ->placeholder('Tanpa Sablon/Bordir')
                                        ->nullable(),
                                    Select::make('edit_sablon_lokasi')
                                        ->label('Titik Lokasi')
                                        ->options(fn () => \App\Models\PrintType::where('category', 'lokasi')->pluck('name', 'name'))
                                        ->searchable()
                                        ->placeholder('Pilih titik lokasi...')
                                        ->nullable(),
                                ]),
                                \Filament\Forms\Components\Textarea::make('edit_sablon_keterangan')
                                    ->label('Keterangan Desain')
                                    ->placeholder('misal: Logo depan dada atau rincian posisi')
                                    ->rows(2)
                                    ->nullable(),
                                Textarea::make('edit_spec_notes')
                                    ->label('Catatan Khusus Spesifikasi Ini (Notes)')
                                    ->placeholder('Contoh: Pakai furing dalam, saku bagian dalam, karet pinggang, dll.')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])->compact(),

                        Section::make('Harga (Custom)')
                            ->visible(fn(OrderItem $record) => $record->size === 'Custom')
                            ->schema([
                                Grid::make(1)->schema([
                                    TextInput::make('edit_custom_price')
                                        ->label('Harga Satuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required(),
                                ])
                            ])->compact(),

                        Section::make('Harga & Jumlah')
                            ->visible(fn(Get $get) => $get('edit_category') === 'jasa')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('edit_jasa_price')
                                        ->label('Harga Jasa')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required(),
                                    TextInput::make('edit_jasa_qty')
                                        ->label('Jumlah (Qty)')
                                        ->numeric()
                                        ->required(),
                                ]),
                            ])->compact(),

                        Section::make('Jumlah & Harga per Ukuran')
                            ->visible(fn(OrderItem $record, Get $get) => in_array($get('edit_category') ?? 'produksi', ['produksi', 'non_produksi']) && $record->size !== 'Custom')
                            ->collapsible()
                            ->schema([
                                Grid::make(12)->schema(function (OrderItem $record) use ($sizeOptions) {
                                    $headers = [
                                        Placeholder::make('hdr_size')
                                            ->hiddenLabel()
                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.8rem; color: #4b5563; font-weight: 700; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px;">Ukuran</div>'))
                                            ->columnSpan(2),
                                        Placeholder::make('hdr_qty')
                                            ->hiddenLabel()
                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.8rem; color: #4b5563; font-weight: 700; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px;">Jumlah (Pcs)</div>'))
                                            ->columnSpan(4),
                                        Placeholder::make('hdr_price')
                                            ->hiddenLabel()
                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-size: 0.8rem; color: #4b5563; font-weight: 700; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px;">Harga Satuan</div>'))
                                            ->columnSpan(6),
                                    ];

                                    $rows = [];
                                    foreach ($sizeOptions as $key => $label) {
                                        $qtyField = TextInput::make("qty_{$key}")
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->placeholder('0')
                                            ->extraInputAttributes(['onclick' => 'this.select()'])
                                            ->columnSpan(4);

                                        $rows = array_merge($rows, [
                                            Placeholder::make("lbl_{$key}")
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString("<div style='font-weight: 600; display: flex; align-items: center; min-height: 36px; color: #374151;'>{$label}</div>"))
                                                ->columnSpan(2),
                                            $qtyField,
                                            TextInput::make("price_{$key}")
                                                ->hiddenLabel()
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->placeholder('0')
                                                ->extraInputAttributes(['onclick' => 'this.select()'])
                                                ->columnSpan(6),
                                        ]);
                                    }

                                    return array_merge($headers, $rows);
                                })
                            ])->compact()
                    ])
                    ->fillForm(function (OrderItem $record) use ($sizeOptions) {
                        $details = $record->size_and_request_details ?? [];

                        $cat = $record->production_category === 'custom' ? 'produksi' : ($record->production_category ?? 'produksi');

                        $formData = [
                            'edit_category' => $cat,
                            'edit_product_name' => $record->product_name,
                            'edit_bahan' => $record->bahan_id,
                            'edit_material_variant_id' => $details['material_variant_id'] ?? null,
                            'edit_sablon_teknik' => $details['sablon_jenis'] ?? null,
                            'edit_sablon_lokasi' => $details['sablon_lokasi'] ?? null,
                            'edit_sablon_keterangan' => $details['sablon_keterangan'] ?? null,
                            'edit_group_label' => $details['group_label'] ?? null,
                            'edit_gender' => $details['gender'] ?? 'L',
                            'edit_model' => $details['model'] ?? 'biasa',
                            'edit_sleeve' => $details['sleeve_model'] ?? 'pendek',
                            'edit_pocket' => $details['pocket_model'] ?? 'tanpa_saku',
                            'edit_button' => $details['button_model'] ?? 'biasa',
                            'edit_collar' => $details['collar_model'] ?? 'biasa',
                            'edit_spec_notes' => $details['spec_notes'] ?? null,
                        ];

                        if ($record->production_category === 'jasa') {
                            $formData['edit_jasa_qty'] = $record->quantity;
                            $formData['edit_jasa_price'] = $record->price;
                        } elseif ($record->size === 'Custom') {
                            $itemsInGroup = $this->getItemsInGroup($record);
                            $formData['edit_custom_qty'] = $itemsInGroup->sum('quantity');
                            $formData['edit_custom_price'] = $record->price;
                        } else {
                            $itemsInGroup = $this->getItemsInGroup($record);
                            $groupDefaultPrice = (int) ($itemsInGroup->where('price', '>', 0)->first()?->price ?? $record->price);

                            foreach ($sizeOptions as $key => $label) {
                                $itemsOfSize = $itemsInGroup->where('size', $key);
                                if ($itemsOfSize->isNotEmpty()) {
                                    $qty = $itemsOfSize->sum('quantity');
                                    $itemPrice = (int) $itemsOfSize->first()->price;
                                    $formData["qty_{$key}"] = $qty;
                                    $formData["price_{$key}"] = ($itemPrice > 0) ? $itemPrice : $groupDefaultPrice;
                                } else {
                                    $formData["qty_{$key}"] = 0;
                                    $formData["price_{$key}"] = 0;
                                }
                            }
                        }
                        return $formData;
                    })
                    ->action(function (OrderItem $record, array $data) use ($sizeOptions) {
                        $details = $record->size_and_request_details ?? [];

                        $newProductName = $data['edit_product_name'] ?? $record->product_name;
                        $newCategory = $data['edit_category'] ?? $record->production_category;
                        $newBahanId = ($newCategory === 'produksi') ? ($data['edit_bahan'] ?? $record->bahan_id) : null;
                        $newMaterialVariantId = ($newCategory === 'produksi') ? ($data['edit_material_variant_id'] ?? ($details['material_variant_id'] ?? null)) : null;

                        // Compile new details
                        $newDetails = $details;
                        $newDetails['material_variant_id'] = $newMaterialVariantId;
                        if (in_array($newCategory, ['produksi', 'custom'])) {
                            $newDetails['group_label'] = $data['edit_group_label'] ?? null;
                            $newDetails['gender'] = $data['edit_gender'] ?? 'L';
                            $newDetails['sleeve_model'] = $data['edit_sleeve'] ?? 'pendek';
                            $newDetails['pocket_model'] = $data['edit_pocket'] ?? 'tanpa_saku';
                            $newDetails['button_model'] = $data['edit_button'] ?? 'biasa';
                            $newDetails['collar_model'] = $data['edit_collar'] ?? 'biasa';
                            $newDetails['model'] = $data['edit_model'] ?? 'biasa';
                            $newDetails['spec_notes'] = $data['edit_spec_notes'] ?? null;
                            $newDetails['sablon_jenis'] = $data['edit_sablon_teknik'] ?? null;
                            $newDetails['sablon_lokasi'] = $data['edit_sablon_lokasi'] ?? null;
                            $newDetails['sablon_keterangan'] = $data['edit_sablon_keterangan'] ?? null;
                        }

                        // Update base info for all existing items in this group
                        $itemsInGroup = $this->getItemsInGroup($record);
                        $groupDefaultPrice = (int) ($itemsInGroup->where('price', '>', 0)->first()?->price ?? $record->price);

                        foreach ($itemsInGroup as $itemToUpdate) {
                            $itemDetails = $itemToUpdate->size_and_request_details ?? [];
                            $itemDetails['material_variant_id'] = $newMaterialVariantId;
                            if (in_array($newCategory, ['produksi', 'custom'])) {
                                $itemDetails['group_label'] = $data['edit_group_label'] ?? null;
                                $itemDetails['gender'] = $data['edit_gender'] ?? 'L';
                                $itemDetails['sleeve_model'] = $data['edit_sleeve'] ?? 'pendek';
                                $itemDetails['pocket_model'] = $data['edit_pocket'] ?? 'tanpa_saku';
                                $itemDetails['button_model'] = $data['edit_button'] ?? 'biasa';
                                $itemDetails['collar_model'] = $data['edit_collar'] ?? 'biasa';
                                $itemDetails['model'] = $data['edit_model'] ?? 'biasa';
                                $itemDetails['spec_notes'] = $data['edit_spec_notes'] ?? null;
                                $itemDetails['sablon_jenis'] = $data['edit_sablon_teknik'] ?? null;
                                $itemDetails['sablon_lokasi'] = $data['edit_sablon_lokasi'] ?? null;
                                $itemDetails['sablon_keterangan'] = $data['edit_sablon_keterangan'] ?? null;
                            }
                            $itemToUpdate->update([
                                'product_name' => $newProductName,
                                'production_category' => $newCategory,
                                'bahan_id' => $newBahanId,
                                'size_and_request_details' => $itemDetails,
                            ]);
                        }

                        if ($newCategory === 'jasa') {
                            $newQty = (int) ($data['edit_jasa_qty'] ?? 1);
                            $newPrice = (int) ($data['edit_jasa_price'] ?? 0);
                            
                            $record->update([
                                'quantity' => $newQty,
                                'price' => $newPrice ?: $record->price,
                            ]);
                        } elseif ($record->size === 'Custom') {
                            $newPrice = (int) ($data['edit_custom_price'] ?? 0);
                            $record->update([
                                'price' => $newPrice ?: $record->price,
                                'size_and_request_details' => $newDetails,
                            ]);
                        } else {
                            foreach ($sizeOptions as $key => $label) {
                                $qty = (int) ($data["qty_{$key}"] ?? 0);
                                $price = (int) ($data["price_{$key}"] ?? 0);
                                if ($price <= 0) {
                                    $price = $groupDefaultPrice;
                                }

                                $itemsOfSize = $itemsInGroup->where('size', $key);
                                $existingItem = $itemsOfSize->first();

                                if ($qty > 0) {
                                    if ($existingItem) {
                                        $existingItem->update([
                                            'product_name' => $newProductName,
                                            'production_category' => $newCategory,
                                            'bahan_id' => $newBahanId,
                                            'quantity' => $qty,
                                            'price' => $price ?: $existingItem->price,
                                            'size_and_request_details' => $newDetails,
                                        ]);

                                        // Consolidate extra items of this size if any
                                        if ($itemsOfSize->count() > 1) {
                                            foreach ($itemsOfSize->slice(1) as $extraItem) {
                                                $extraItem->delete();
                                            }
                                        }
                                    } else {
                                        $record->order->orderItems()->create([
                                            'product_name' => $newProductName,
                                            'production_category' => $newCategory,
                                            'bahan_id' => $newBahanId,
                                            'size' => $key,
                                            'price' => $price ?: $record->price,
                                            'quantity' => $qty,
                                            'design_status' => $record->order->orderItems()->where('product_name', $newProductName)->where('design_status', 'approved')->exists() && !in_array($record->order->status, ['draft', 'antrian']) ? 'approved' : 'pending',
                                            'size_and_request_details' => $newDetails,
                                        ]);
                                    }
                                } else {
                                    foreach ($itemsOfSize as $itemToDelete) {
                                        $itemToDelete->delete();
                                    }
                                }
                            }
                        }

                        $this->refreshOrderData();
                        Notification::make()->success()->title('Ukuran & Spesifikasi berhasil diperbarui!')->send();
                    }),

                Action::make('edit_measurements')
                    ->label('Ukur')
                    ->icon('heroicon-m-viewfinder-circle')
                    ->color('warning')
                    ->closeModalByClickingAway(false)
                    ->modalHeading(fn(OrderItem $record) => 'Rekam Ukuran Badan: ' . ($record->recipient_name ?: 'Orang'))
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Simpan Ukuran'))
                    ->modalWidth('xl')
                    ->visible(fn(OrderItem $record) => $record->size === 'Custom' && in_array(auth()->user()->role, ['owner', 'admin']) && $record->canBeEdited())
                    ->form([
                        Grid::make(3)->schema([
                            TextInput::make('LD')->label('LD')->numeric()->suffix('cm'),
                            TextInput::make('PB')->label('PB')->numeric()->suffix('cm'),
                            TextInput::make('PL')->label('PL')->numeric()->suffix('cm'),
                            TextInput::make('LB')->label('LB')->numeric()->suffix('cm'),
                            TextInput::make('LP')->label('LP')->numeric()->suffix('cm'),
                            TextInput::make('LPh')->label('LPh')->numeric()->suffix('cm'),
                        ]),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Catatan Khusus')
                            ->rows(2),
                    ])
                    ->fillForm(fn(OrderItem $record) => $record->size_and_request_details ?? [])
                    ->action(function (OrderItem $record, array $data): void {
                        $details = $record->size_and_request_details ?? [];
                        $record->update([
                            'size_and_request_details' => array_merge($details, $data),
                        ]);
                        Notification::make()->success()->title('Ukuran disimpan!')->send();
                    }),

                DeleteAction::make()
                    ->visible(fn(OrderItem $record) => in_array(auth()->user()->role, ['owner', 'admin']) && $record->canBeEdited())
                    ->action(function (OrderItem $record) {
                        if ($record->size === 'Custom') {
                            $record->delete();
                        } else {
                            $itemsInGroup = $this->getItemsInGroup($record);
                            foreach ($itemsInGroup as $item) {
                                $item->delete();
                            }
                        }
                    })
                    ->after(fn() => $this->refreshOrderData()),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->iconButton()
                ->color('gray')
                ->extraAttributes([
                    'class' => 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg p-1.5'
                ])
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('size')
                    ->label('Ukuran')
                    ->options($sizeOptions + ['Custom' => 'Ukur Badan']),
                \Filament\Tables\Filters\SelectFilter::make('gender')
                    ->label('JK')
                    ->options($genderOptions),
                \Filament\Tables\Filters\SelectFilter::make('sleeve_model')
                    ->label('Lengan')
                    ->options($sleeveOptions),
                \Filament\Tables\Filters\SelectFilter::make('pocket_model')
                    ->label('Saku')
                    ->options($pocketOptions),
                \Filament\Tables\Filters\SelectFilter::make('button_model')
                    ->label('Kancing')
                    ->options($buttonOptions),
                \Filament\Tables\Filters\SelectFilter::make('collar_model')
                    ->label('Kerah')
                    ->options($collarOptions),
                \Filament\Tables\Filters\SelectFilter::make('model')
                    ->label('Model')
                    ->options($modelOptions),
            ], layout: \Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormWidth('md')
            ->bulkActions([
                BulkAction::make('update_variation')
                    ->label('Update Variasi (Massal)')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->visible(fn () => (auth()->user()->role === 'owner' || (auth()->user()->role === 'admin' && !in_array($this->order->status, ['diproses', 'selesai']))))
                    ->form(function() use ($genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions, $collarOptions, $modelOptions) {
                        return [
                            Section::make('Harga (Opsional)')
                                ->schema([
                                    TextInput::make('bulk_price')
                                        ->label('Harga Satuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Abaikan jika tidak diubah')
                                    ])->compact(),
                            Section::make('Spesifikasi & Desain')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('gender')->label('Gender / JK')->options($genderOptions),
                                        Select::make('sleeve_model')->label('Lengan')->options($sleeveOptions),
                                        Select::make('pocket_model')->label('Saku')->options($pocketOptions),
                                    ]),
                                    Grid::make(3)->schema([
                                        Select::make('button_model')->label('Kancing')->options($buttonOptions),
                                        Select::make('collar_model')->label('Kerah')->options($collarOptions),
                                        Select::make('model')->label('Model')->options($modelOptions),
                                    ]),
                                    Grid::make(2)->schema([
                                        Select::make('sablon_jenis')
                                            ->label('Teknik Sablon / Bordir')
                                            ->options(\App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                            ->searchable()
                                            ->placeholder('Abaikan jika tidak diubah'),
                                        Select::make('sablon_lokasi')
                                            ->label('Titik Lokasi')
                                            ->options(\App\Models\PrintType::where('category', 'lokasi')->pluck('name', 'name'))
                                            ->searchable()
                                            ->placeholder('Abaikan jika tidak diubah'),
                                    ]),
                                    \Filament\Forms\Components\Textarea::make('sablon_keterangan')
                                        ->label('Keterangan Desain')
                                        ->placeholder('Abaikan jika tidak diubah')
                                        ->rows(2),
                                ])->compact(),
                        ];
                    })
                    ->action(function (Collection $records, array $data) {
                        foreach ($records as $record) {
                            if (isset($data['bulk_price']) && filled($data['bulk_price'])) {
                                $record->update(['price' => $data['bulk_price']]);
                            }

                            if (in_array($record->production_category, ['produksi', 'custom'])) {
                                $details = $record->size_and_request_details ?? [];
                                foreach (['gender', 'sleeve_model', 'pocket_model', 'button_model', 'collar_model', 'model', 'sablon_jenis', 'sablon_lokasi', 'sablon_keterangan'] as $field) {
                                    if (isset($data[$field]) && filled($data[$field])) {
                                        $details[$field] = $data[$field];
                                        if ($field === 'model') {
                                            $details['is_tunic'] = ($data[$field] === 'tunic');
                                        }
                                    }
                                }
                                $record->update(['size_and_request_details' => $details]);
                            }
                        }
                        Notification::make()->success()->title('Data massal diperbarui')->send();
                        $this->refreshOrderData();
                    }),
                DeleteBulkAction::make()
                    ->visible(fn () => (auth()->user()->role === 'owner' || (auth()->user()->role === 'admin' && !in_array($this->order->status, ['diproses', 'selesai']))))
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record->size === 'Custom') {
                                $record->delete();
                            } else {
                                $itemsInGroup = $this->getItemsInGroup($record);
                                foreach ($itemsInGroup as $item) {
                                    $item->delete();
                                }
                            }
                        }
                        Notification::make()->success()->title('Data massal berhasil dihapus')->send();
                        $this->refreshOrderData();
                    }),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('item_group_identity')
                    ->label('Kelompok Spesifikasi')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        $categoryLabel = match ($record->production_category) {
                            'custom' => 'Produksi',
                            'non_produksi' => 'Non-Produksi',
                            'jasa' => 'Jasa',
                            default => 'Produksi',
                        };
                        
                        $sizeType = ($record->size === 'Custom') ? 'Ukur Badan' : 'Size Toko';
                        return in_array($record->production_category, ['non_produksi', 'jasa'])
                            ? "{$record->product_name} - {$categoryLabel}"
                            : "{$record->product_name} ({$sizeType}) - {$categoryLabel}";
                    })
                    ->getDescriptionUsing(function ($record) {
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        $hasTasks = \App\Models\ProductionTask::whereIn('order_item_id', $groupItemIds)->exists();
                        return $hasTasks ? null : '⚠️ Belum Ditugaskan';
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('item_group_identity')
            ->groupingSettingsHidden()
            ->paginated(false)
            ->defaultSort('product_name', 'asc')
            ->defaultKeySort(false);
    }

    protected function getNewItemDesignStatus(string $productName): string
    {
        if (in_array($this->order->status, ['draft', 'antrian'])) {
            return 'pending';
        }

        $existsApproved = $this->order->orderItems()
            ->where('product_name', $productName)
            ->where('design_status', 'approved')
            ->exists();

        return $existsApproved ? 'approved' : 'pending';
    }

    protected function getItemsInGroup(OrderItem $record): \Illuminate\Support\Collection
    {
        $recordDetails = $record->size_and_request_details ?? [];
        
        $isProduction = in_array($record->production_category, ['produksi', 'custom']);
        
        $recordSleeve = $isProduction ? ($recordDetails['sleeve_model'] ?? 'pendek') : null;
        $recordPocket = $isProduction ? ($recordDetails['pocket_model'] ?? 'tanpa_saku') : null;
        $recordButton = $isProduction ? ($recordDetails['button_model'] ?? 'biasa') : null;
        $recordCollar = $isProduction ? ($recordDetails['collar_model'] ?? 'biasa') : null;
        $recordModel = $isProduction ? ($recordDetails['model'] ?? 'biasa') : null;
        $recordGender = $isProduction ? ($recordDetails['gender'] ?? 'L') : null;
        
        $recordMaterialVar = $recordDetails['material_variant_id'] ?? null;
        $recordProductVar = $recordDetails['product_variant_id'] ?? null;
        
        $recordSablonJenis = $recordDetails['sablon_jenis'] ?? null;
        $recordSablonLokasi = $recordDetails['sablon_lokasi'] ?? null;
        $recordSablonKeterangan = $recordDetails['sablon_keterangan'] ?? null;

        $query = OrderItem::where('order_id', $record->order_id)
            ->where('product_name', $record->product_name)
            ->where('production_category', $record->production_category);

        if ($record->bahan_id === null) {
            $query->whereNull('bahan_id');
        } else {
            $query->where('bahan_id', $record->bahan_id);
        }

        return $query->get()
            ->filter(function ($item) use (
                $isProduction, $recordSleeve, $recordPocket, $recordButton, $recordCollar, $recordModel, $recordGender,
                $recordMaterialVar, $recordProductVar, $recordSablonJenis, $recordSablonLokasi, $recordSablonKeterangan
            ) {
                $itemDetails = $item->size_and_request_details ?? [];
                
                $itemSleeve = $isProduction ? ($itemDetails['sleeve_model'] ?? 'pendek') : null;
                $itemPocket = $isProduction ? ($itemDetails['pocket_model'] ?? 'tanpa_saku') : null;
                $itemButton = $isProduction ? ($itemDetails['button_model'] ?? 'biasa') : null;
                $itemCollar = $isProduction ? ($itemDetails['collar_model'] ?? 'biasa') : null;
                $itemModel = $isProduction ? ($itemDetails['model'] ?? 'biasa') : null;
                $itemGender = $isProduction ? ($itemDetails['gender'] ?? 'L') : null;
                
                $itemMaterialVar = $itemDetails['material_variant_id'] ?? null;
                $itemProductVar = $itemDetails['product_variant_id'] ?? null;
                
                $itemSablonJenis = $itemDetails['sablon_jenis'] ?? null;
                $itemSablonLokasi = $itemDetails['sablon_lokasi'] ?? null;
                $itemSablonKeterangan = $itemDetails['sablon_keterangan'] ?? null;

                return $itemSleeve === $recordSleeve &&
                       $itemPocket === $recordPocket &&
                       $itemButton === $recordButton &&
                       $itemCollar === $recordCollar &&
                       $itemModel === $recordModel &&
                       $itemGender === $recordGender &&
                       $itemMaterialVar == $recordMaterialVar &&
                       $itemProductVar == $recordProductVar &&
                       $itemSablonJenis === $recordSablonJenis &&
                       $itemSablonLokasi === $recordSablonLokasi &&
                       $itemSablonKeterangan === $recordSablonKeterangan;
            });
    }

    protected function refreshOrderData(): void
    {
        // Sum price * quantity for all items
        $subtotal = $this->order->orderItems()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));
        $this->order->update(['subtotal' => (int) $subtotal]);

        $this->dispatch('refreshOrderSummary', subtotal: (int) $subtotal);
    }

    public function updateRecipientName($id, $name): void
    {
        $item = OrderItem::find($id);
        if ($item) {
            $item->update(['recipient_name' => $name]);
            $this->refreshOrderData();
            Notification::make()->success()->title('Nama penerima diperbarui!')->send();
        }
    }

    public function render()
    {
        return view('livewire.integrated-order-items-table');
    }
}
