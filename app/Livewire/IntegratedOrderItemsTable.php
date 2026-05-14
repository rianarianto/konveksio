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

    public function mount(Order $order): void
    {
        $this->order = $order;
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
            'produksi' => 'Konveksi',
            'non_produksi' => 'Baju Jadi',
            'jasa' => 'Jasa',
        ];

        $genderOptions = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
        $sleeveOptions = ['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4'];
        $pocketOptions = ['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok'];
        $buttonOptions = ['biasa' => 'Biasa', 'snap' => 'Snap/Tertutup'];

        return $table
            ->query(
                OrderItem::query()
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
                            ->select('order_items.*')
                            ->selectRaw('materials.name as bahan_name')
                            ->selectRaw('COALESCE(material_variants.color_name, product_variants.color_name) as varian_warna')
                            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.gender')), 'L') as gender")
                            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sleeve_model')), 'pendek') as sleeve_model")
                            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.pocket_model')), 'tanpa_saku') as pocket_model")
                            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.button_model')), 'biasa') as button_model")
                            ->selectRaw("
                                CONCAT(
                                    product_name, ' | ',
                                    CASE 
                                        WHEN production_category = 'custom' THEN 'Konveksi (Ukur)' 
                                        WHEN production_category = 'non_produksi' THEN 'Baju Jadi'
                                        ELSE 'Konveksi' 
                                    END, ' | ',
                                    CASE 
                                        WHEN production_category = 'non_produksi' THEN COALESCE(product_variants.color_name, 'Tanpa Warna')
                                        ELSE COALESCE(materials.name, 'Tanpa Bahan')
                                    END,
                                    CASE 
                                        WHEN production_category = 'non_produksi' THEN ''
                                        ELSE CONCAT(' | ', COALESCE(material_variants.color_name, 'Tanpa Warna'))
                                    END,
                                    CASE 
                                        WHEN production_category = 'non_produksi' OR JSON_EXTRACT(size_and_request_details, '$.sablon_jenis') IS NULL THEN ''
                                        ELSE CONCAT(' | ', 
                                            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sablon_jenis')), 'Tanpa Sablon/Bordir'), ' - ',
                                            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.sablon_lokasi')), '-')
                                        )
                                    END
                                ) as item_group_identity
                            "),
                        'order_items'
                    )
            )
            ->columns([
                TextInputColumn::make('recipient_name')
                    ->label('Penerima')
                    ->placeholder('Nama...')
                    ->width('150px')
                    ->searchable()
                    ->sortable(),

                SelectColumn::make('size')
                    ->label('Size')
                    ->options($sizeOptions + ['Custom' => 'Ukur Badan'])
                    ->sortable(),

                SelectColumn::make('gender')
                    ->label('JK')
                    ->options($genderOptions)
                    ->sortable(),

                SelectColumn::make('sleeve_model')
                    ->label('Lengan')
                    ->options($sleeveOptions)
                    ->sortable(),

                SelectColumn::make('pocket_model')
                    ->label('Saku')
                    ->options($pocketOptions)
                    ->sortable(),

                SelectColumn::make('button_model')
                    ->label('Kancing')
                    ->options($buttonOptions)
                    ->sortable(),

                \Filament\Tables\Columns\ToggleColumn::make('is_tunic')
                    ->label('Tunik?')
                    ->sortable(),

                TextInputColumn::make('price')
                    ->label('Harga')
                    ->placeholder('Rp 0')
                    ->width('120px')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable()
                    ->afterStateUpdated(function () {
                        $this->refreshOrderData();
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('item_group_identity')
                    ->label('')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function (OrderItem $record) {
                        $title = $record->item_group_identity;
                        $useStock = $record->size_and_request_details['use_stock'] ?? false;
                        
                        if ($useStock) {
                            $totalStockUsed = OrderItem::where('order_id', $this->order->id)
                                ->where('product_name', $record->product_name)
                                ->where('production_category', $record->production_category)
                                ->get()
                                ->sum(fn($item) => (float) ($item->size_and_request_details['stock_qty_used'] ?? 0));

                            $unit = 'pcs';
                            if ($record->production_category === 'produksi' && $record->bahan_id) {
                                $unit = \App\Models\Material::find($record->bahan_id)?->unit ?? 'm';
                            }

                            $formattedQty = number_format($totalStockUsed, ($totalStockUsed == (int)$totalStockUsed ? 0 : 1), ',', '.');
                            $stockIndicator = "<span class='inline-flex items-center gap-1 rounded-md bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 font-bold'>(📦 {$formattedQty}{$unit})</span>";

                            $parts = explode(' | ', $title);
                            if (count($parts) >= 3) {
                                $parts[2] = $parts[2] . ' ' . $stockIndicator;
                            } else {
                                $parts[count($parts) - 1] .= ' ' . $stockIndicator;
                            }
                            
                            $newTitle = implode(' | ', $parts);
                            return new \Illuminate\Support\HtmlString("<span class='flex items-center gap-1.5'>{$newTitle}</span>");
                        }
                        
                        return $title;
                    })
            )
            ->headerActions([
                Action::make('bulk_generate')
                    ->label('Bulk Generate / Tambah Produk')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Tambahkan ke Pesanan'))
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions, $genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions, $tenantId) {
                        return [
                            Section::make('Kategori & Dasar')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('bulk_category')
                                            ->label('Pilih Kategori')
                                            ->options($categoryOptions)
                                            ->default('produksi')
                                            ->required()
                                            ->live(),
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

                                                    if ($category === 'non_produksi') {
                                                        // Restore Baju Jadi specs
                                                        $variantId = $details['product_variant_id'] ?? null;
                                                        $productId = $details['supplier_product'] ?? null;
                                                        
                                                        // Fallback for older items that only stored variant_id
                                                        if (!$productId && $variantId) {
                                                            $productId = \App\Models\ProductVariant::find($variantId)?->product_id;
                                                        }

                                                        $set('bulk_product_id', (string) $productId);
                                                        $set('bulk_price_non', $existing->price);
                                                        
                                                        if ($variantId) {
                                                            $colorName = \App\Models\ProductVariant::find($variantId)?->color_name;
                                                            $set('bulk_product_color', $colorName);
                                                        }
                                                    } elseif ($category === 'jasa') {
                                                        $set('bulk_price_jasa', $existing->price);
                                                        $set('bulk_qty_jasa', $existing->quantity);
                                                    } else {
                                                        // Restore Konveksi specs
                                                        $set('bulk_price', $existing->price);
                                                        $set('bulk_bahan', $existing->bahan_id);
                                                        $set('bulk_material_variant_id', $details['material_variant_id'] ?? null);
                                                        $set('bulk_gender', $details['gender'] ?? 'L');
                                                        $set('bulk_sleeve', $details['sleeve_model'] ?? 'pendek');
                                                        $set('bulk_pocket', $details['pocket_model'] ?? 'tanpa_saku');
                                                        $set('bulk_button', $details['button_model'] ?? 'biasa');
                                                        $set('bulk_is_tunic', (bool) ($details['is_tunic'] ?? false));
                                                        $set('bulk_sablon_teknik', $details['sablon_jenis'] ?? null);
                                                        $set('bulk_sablon_lokasi', $details['sablon_lokasi'] ?? null);
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
                                            ->live()
                                            ->afterStateUpdated(fn(Set $set) => $set('bulk_material_variant_id', null)),
                                        Select::make('bulk_material_variant_id')
                                            ->label('Warna / Varian')
                                            ->allowHtml()
                                            ->options(function (Get $get) {
                                                $bahanId = $get('bulk_bahan');
                                                if (!$bahanId) return [];
                                                return \App\Models\MaterialVariant::where('material_id', $bahanId)
                                                    ->get()
                                                    ->mapWithKeys(fn($v) => [
                                                        $v->id => "<div class='flex items-center gap-2'><div class='w-4 h-4 rounded-full border border-gray-300' style='background-color: {$v->color_code}'></div> {$v->color_name}</div>"
                                                    ]);
                                            })
                                            ->searchable()
                                            ->live()
                                            ->visible(fn(Get $get) => filled($get('bulk_bahan'))),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('bulk_stock_qty')
                                            ->label('Jml Bahan Terpakai')
                                            ->numeric()
                                            ->placeholder('0')
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($get('bulk_category') !== 'produksi') return;
                                                $variantId = $get('bulk_material_variant_id');
                                                if (!$variantId) return;
                                                
                                                $dbStock = (float) (\App\Models\MaterialVariant::find($variantId)?->current_stock ?? 0);
                                                $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                                    ->get()
                                                    ->filter(fn($item) => ($item->size_and_request_details['material_variant_id'] ?? null) == $variantId)
                                                    ->sum(fn($item) => (float) ($item->size_and_request_details['stock_qty_used'] ?? 0));
                                                
                                                $max = max(0, $dbStock - $usedInOrder);
                                                if ($state > $max) {
                                                    $set('bulk_stock_qty', $max);
                                                    Notification::make()
                                                        ->title('Jumlah melebihi stok tersedia!')
                                                        ->body("Sisa stok yang bisa digunakan di pesanan ini adalah {$max}")
                                                        ->warning()
                                                        ->send();
                                                }
                                            })
                                            ->helperText(function(Get $get) {
                                                if ($get('bulk_category') !== 'produksi') return "";
                                                $variantId = $get('bulk_material_variant_id');
                                                $v = \App\Models\MaterialVariant::with('material')->find($variantId);
                                                if (!$v) return "";
                                                
                                                $dbStock = (float) ($v->current_stock ?? 0);
                                                $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                                    ->get()
                                                    ->filter(fn($item) => ($item->size_and_request_details['material_variant_id'] ?? null) == $variantId)
                                                    ->sum(fn($item) => (float) ($item->size_and_request_details['stock_qty_used'] ?? 0));
                                                
                                                $available = max(0, $dbStock - $usedInOrder);
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
                                    Grid::make(2)->schema([
                                        Select::make('bulk_sablon_teknik')
                                            ->label('Teknik Sablon / Bordir')
                                            ->options(\App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                            ->default('Bordir')
                                            ->searchable(),
                                        Select::make('bulk_sablon_lokasi')
                                            ->label('Posisi / Lokasi')
                                            ->options(\App\Filament\Resources\Orders\OrderResource::$lokasiSablonOptions)
                                            ->default('Dada Kiri')
                                            ->searchable(),
                                    ]),
                                    Grid::make(4)->schema([
                                        Select::make('bulk_gender')->label('Gender')->options($genderOptions)->default('L'),
                                        Select::make('bulk_sleeve')->label('Lengan')->options($sleeveOptions)->default('pendek'),
                                        Select::make('bulk_pocket')->label('Saku')->options($pocketOptions)->default('tanpa_saku'),
                                        Select::make('bulk_button')->label('Kancing')->options($buttonOptions)->default('biasa'),
                                    ]),
                                    Toggle::make('bulk_is_tunic')->label('Tunik / Gamis?')->default(false)
                                ])->visible(fn(Get $get) => $get('bulk_category') === 'produksi')->compact(),

                            Section::make('Detail Baju Jadi / Jasa')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('bulk_product_id')
                                            ->label('Pilih Produk Katalog')
                                            ->options(Product::where('shop_id', $tenantId)->pluck('name', 'id'))
                                            ->searchable()->live()
                                            ->afterStateUpdated(fn($state, Set $set) => $set('bulk_product_name', Product::find($state)?->name))
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),
                                        
                                        Select::make('bulk_product_color')
                                            ->label('Pilih Warna')
                                            ->options(function (Get $get) {
                                                $productId = $get('bulk_product_id');
                                                if (!$productId) return [];
                                                return \App\Models\ProductVariant::where('product_id', $productId)
                                                    ->whereNotNull('color_name')
                                                    ->distinct()
                                                    ->pluck('color_name', 'color_name');
                                            })
                                            ->searchable()
                                            ->live()
                                            ->required(fn(Get $get) => $get('bulk_category') === 'non_produksi')
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),

                                        TextInput::make('bulk_price_non')->label('Harga Jual')->numeric()->prefix('Rp')->placeholder('0')
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),
                                        TextInput::make('bulk_price_jasa')->label('Harga Jasa')->numeric()->prefix('Rp')->placeholder('0')
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'jasa'),
                                        TextInput::make('bulk_qty_jasa')->label('Jumlah (Qty)')->numeric()->default(1)
                                            ->visible(fn(Get $get) => $get('bulk_category') === 'jasa'),
                                    ]),
                                ])->visible(fn(Get $get) => in_array($get('bulk_category'), ['non_produksi', 'jasa']))->compact(),

                            Section::make('Jumlah per Ukuran')
                                ->schema([
                                    Grid::make(4)->schema(function (Get $get) use ($sizeOptions) {
                                        $category = $get('bulk_category');
                                        $productId = $get('bulk_product_id');
                                        $colorName = $get('bulk_product_color');

                                        // For Baju Jadi: only show sizes that exist for selected product+color
                                        if ($category === 'non_produksi' && $productId && $colorName) {
                                            $variants = \App\Models\ProductVariant::where('product_id', $productId)
                                                ->where('color_name', $colorName)
                                                ->get()
                                                ->keyBy('size');

                                            return collect($sizeOptions)->map(function ($label, $key) use ($variants, $productId) {
                                                $variant = $variants->get($key);
                                                if (!$variant) return null;

                                                // Calculate stock used in current order for this variant
                                                $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                                    ->where('production_category', 'non_produksi')
                                                    ->get()
                                                    ->filter(fn($item) => ($item->size_and_request_details['product_variant_id'] ?? null) == $variant->id)
                                                    ->count();
                                                
                                                $available = max(0, $variant->stock - $usedInOrder);

                                                return TextInput::make("qty_{$key}")
                                                    ->label($label)
                                                    ->numeric()
                                                    ->placeholder('0')
                                                    ->helperText(function() use ($available, $usedInOrder) {
                                                        $text = "Tersedia: {$available} pcs";
                                                        if ($usedInOrder > 0) {
                                                            $text .= " (Terpakai {$usedInOrder} di pesanan ini)";
                                                        }
                                                        return $text;
                                                    })
                                                    ->extraInputAttributes(['onclick' => 'this.select()']);
                                            })->filter()->values()->toArray();
                                        }

                                        // For Konveksi: show all standard sizes + custom
                                        return [
                                            ...collect($sizeOptions)->map(function ($label, $key) {
                                                return TextInput::make("qty_{$key}")
                                                    ->label($label)
                                                    ->numeric()
                                                    ->placeholder('0')
                                                    ->extraInputAttributes(['onclick' => 'this.select()']);
                                            })->values()->toArray(),
                                            TextInput::make('qty_custom')
                                                ->label('Custom')
                                                ->numeric()
                                                ->live()
                                                ->placeholder('0')
                                                ->visible(fn(Get $get) => $get('bulk_category') === 'produksi')
                                                ->extraInputAttributes(['onclick' => 'this.select()']),
                                        ];
                                    }),
                                ])->visible(fn(Get $get) => in_array($get('bulk_category'), ['produksi', 'non_produksi']))->compact(),

                            Section::make('Harga Satuan')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('bulk_price')->label('Harga Standar (Rp)')->numeric()->prefix('Rp')->placeholder('0'),
                                        TextInput::make('bulk_price_custom')->label('Harga Custom (Rp)')->numeric()->prefix('Rp')->placeholder('0')
                                            ->visible(fn(Get $get) => (int) $get('qty_custom') > 0),
                                    ]),
                                ])->visible(fn(Get $get) => $get('bulk_category') === 'produksi')->compact(),
                        ];
                    })
                    ->modalWidth('4xl')
                    ->action(function (array $data) use ($sizeOptions) {
                        $category = $data['bulk_category'];
                        $productName = $data['bulk_product_name'];
                        if ($category === 'jasa') {
                            $this->order->orderItems()->create([
                                'product_name' => $productName,
                                'production_category' => 'jasa',
                                'price' => (int) ($data['bulk_price_jasa'] ?? 0),
                                'quantity' => (int) ($data['bulk_qty_jasa'] ?? 1),
                            ]);
                        } else {
                            $totalToGenerate = 0;
                            foreach ($sizeOptions as $key => $label) {
                                $totalToGenerate += (int) ($data["qty_{$key}"] ?? 0);
                            }
                            $totalToGenerate += (int) ($data['qty_custom'] ?? 0);

                            if ($totalToGenerate === 0) return;

                            foreach ($sizeOptions as $key => $label) {
                                $qty = (int) ($data["qty_{$key}"] ?? 0);
                                if ($qty <= 0) continue;

                                $variantId = null;
                                $useStock = false;
                                $stockQtyUsed = 0;

                                if ($category === 'produksi') {
                                    $variantId = $data['bulk_material_variant_id'] ?? null;
                                    $useStock = filled($data['bulk_stock_qty'] ?? null);
                                    $stockQtyUsed = $useStock ? ($data['bulk_stock_qty'] / $totalToGenerate) : 0;
                                } else {
                                    $v = \App\Models\ProductVariant::where('product_id', $data['bulk_product_id'] ?? null)
                                        ->where('color_name', $data['bulk_product_color'] ?? null)
                                        ->where('size', $key)
                                        ->first();
                                    $variantId = $v?->id;
                                    $useStock = (bool) $variantId;
                                    $stockQtyUsed = $useStock ? 1 : 0;

                                    if ($useStock) {
                                        // Real-time stock validation
                                        $usedInOrder = OrderItem::where('order_id', $this->order->id)
                                            ->get()
                                            ->filter(fn($item) => ($item->size_and_request_details['product_variant_id'] ?? null) == $variantId)
                                            ->count();
                                        
                                        $available = max(0, $v->stock - $usedInOrder);

                                        if ($qty > $available) {
                                            Notification::make()
                                                ->title("Stok tidak cukup untuk {$v->color_name} - {$v->size}!")
                                                ->body("Tersedia: {$available} pcs. Anda mencoba menambah {$qty} pcs.")
                                                ->danger()
                                                ->send();
                                            return; // Halt the whole bulk process for safety
                                        }
                                    }
                                }

                                for ($i = 0; $i < $qty; $i++) {
                                    $this->order->orderItems()->create([
                                        'product_name' => $productName,
                                        'production_category' => $category,
                                        'bahan_id' => ($category === 'produksi') ? ($data['bulk_bahan'] ?? null) : null,
                                        'size' => $key,
                                        'price' => (int) ($category === 'produksi' ? ($data['bulk_price'] ?? 0) : ($data['bulk_price_non'] ?? 0)),
                                        'quantity' => 1,
                                        'size_and_request_details' => [
                                            'gender' => $data['bulk_gender'] ?? 'L',
                                            'sleeve_model' => $data['bulk_sleeve'] ?? 'pendek',
                                            'pocket_model' => $data['bulk_pocket'] ?? 'tanpa_saku',
                                            'button_model' => $data['bulk_button'] ?? 'biasa',
                                            'is_tunic' => (bool) ($data['bulk_is_tunic'] ?? false),
                                            'sablon_jenis' => $data['bulk_sablon_teknik'] ?? null,
                                            'sablon_lokasi' => $data['bulk_sablon_lokasi'] ?? null,
                                            'material_variant_id' => ($category === 'produksi') ? $variantId : null,
                                            'product_variant_id' => ($category === 'non_produksi') ? $variantId : null,
                                            'supplier_product' => ($category === 'non_produksi') ? ($data['bulk_product_id'] ?? null) : null,
                                            'use_stock' => $useStock,
                                            'stock_qty_used' => $stockQtyUsed,
                                        ],
                                    ]);
                                }
                            }
                            
                            $cQty = (int) ($data['qty_custom'] ?? 0);
                            for ($i = 0; $i < $cQty; $i++) {
                                $this->order->orderItems()->create([
                                    'product_name' => $productName,
                                    'production_category' => $category,
                                    'size' => 'Custom',
                                    'quantity' => 1,
                                    'price' => (int) ($category === 'produksi' ? ($data['bulk_price_custom'] ?? $data['bulk_price'] ?? 0) : ($data['bulk_price_non'] ?? 0)),
                                    'size_and_request_details' => [
                                        'material_variant_id' => ($category === 'produksi') ? ($data['bulk_material_variant_id'] ?? null) : null,
                                        'use_stock' => ($category === 'produksi' && filled($data['bulk_stock_qty'] ?? null)),
                                        'stock_qty_used' => ($category === 'produksi' && filled($data['bulk_stock_qty'] ?? null)) ? ($data['bulk_stock_qty'] / $totalToGenerate) : 0,
                                    ]
                                ]);
                            }
                        }
                        $this->refreshOrderData();
                        Notification::make()->success()->title('Item berhasil ditambahkan!')->send();
                    }),
            ])
            ->actions([
                Action::make('edit_measurements')
                    ->label('Ukur')
                    ->icon('heroicon-m-viewfinder-circle')
                    ->color('warning')
                    ->modalHeading('Rekam Ukuran Badan (cm)')
                    ->modalSubmitAction(fn ($action) => $action->color('primary')->label('Simpan Ukuran'))
                    ->modalWidth('2xl')
                    ->visible(fn(OrderItem $record) => $record->size === 'Custom')
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
                        Notification::make()->success()->title('Ukuran disimpan')->send();
                    }),
                DeleteAction::make()
                    ->after(fn() => $this->refreshOrderData()),
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
            ], layout: \Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormWidth('md')
            ->bulkActions([
                BulkAction::make('update_variation')
                    ->label('Update Variasi (Massal)')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->form(function() use ($genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions) {
                        return [
                            Grid::make(2)->schema([
                                Select::make('gender')->label('JK')->options($genderOptions),
                                Select::make('sleeve_model')->label('Lengan')->options($sleeveOptions),
                                Select::make('pocket_model')->label('Saku')->options($pocketOptions),
                                Select::make('button_model')->label('Kancing')->options($buttonOptions),
                                Toggle::make('is_tunic')->label('Tunik?'),
                            ]),
                        ];
                    })
                    ->action(function (Collection $records, array $data) {
                        foreach ($records as $record) {
                            $details = $record->size_and_request_details ?? [];
                            foreach (['gender', 'sleeve_model', 'pocket_model', 'button_model', 'is_tunic'] as $field) {
                                if (isset($data[$field]) && filled($data[$field])) {
                                    $details[$field] = $data[$field];
                                }
                            }
                            $record->update(['size_and_request_details' => $details]);
                        }
                        Notification::make()->success()->title('Variasi diperbarui')->send();
                        $this->refreshOrderData();
                    }),
                DeleteBulkAction::make()
                    ->after(fn() => $this->refreshOrderData()),
            ])
            ->paginated(false);
    }

    protected function refreshOrderData(): void
    {
        // Sum price * quantity for all items
        $subtotal = $this->order->orderItems()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));
        $this->order->update(['subtotal' => (int) $subtotal]);

        $this->dispatch('refreshOrderSummary', subtotal: (int) $subtotal);
    }

    public function render()
    {
        return view('livewire.integrated-order-items-table');
    }
}
