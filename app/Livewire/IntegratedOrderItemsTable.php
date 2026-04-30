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

class IntegratedOrderItemsTable extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public Order $order;
    public array $selectedGroups = ['product_name', 'category', 'gender'];

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
                            ->select('order_items.*', 'materials.name as bahan_name')
                            ->selectRaw("
                                CONCAT_WS(' | ', 
                                    " . (in_array('product_name', $this->selectedGroups) ? "product_name" : "NULL") . ",
                                    " . (in_array('category', $this->selectedGroups) ? "CASE WHEN production_category = 'custom' THEN 'produksi' ELSE production_category END" : "NULL") . ",
                                    " . (in_array('gender', $this->selectedGroups) ? "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(size_and_request_details, '$.gender')), 'L')" : "NULL") . ",
                                    " . (in_array('bahan', $this->selectedGroups) ? "COALESCE(materials.name, 'Tanpa Bahan')" : "NULL") . ",
                                    " . (in_array('size', $this->selectedGroups) ? "size" : "NULL") . ",
                                    " . (in_array('recipient', $this->selectedGroups) ? "COALESCE(recipient_name, 'Tanpa Penerima')" : "NULL") . "
                                ) as dynamic_group
                            "),
                        'order_items'
                    )
            )
            ->columns([
                TextInputColumn::make('recipient_name')
                    ->label('Penerima')
                    ->placeholder('Nama...')
                    ->sortable()
                    ->searchable(),

                TextInputColumn::make('product_name')
                    ->label('Nama / Item')
                    ->placeholder('Produk...')
                    ->sortable()
                    ->searchable()
                    ->rules(['required', 'max:255']),

                SelectColumn::make('size')
                    ->label('Size')
                    ->options($sizeOptions + ['Custom' => 'Ukur Badan'])
                    ->sortable(),

                TextColumn::make('measurement_status')
                    ->label('Status Ukur')
                    ->badge()
                    ->color(fn(OrderItem $record) => $record->size === 'Custom' ? (filled($record->size_and_request_details['LD'] ?? null) ? 'success' : 'danger') : 'gray')
                    ->getStateUsing(fn(OrderItem $record) => $record->size === 'Custom' ? (filled($record->size_and_request_details['LD'] ?? null) ? 'Terukur' : 'Belum Ukur') : 'Standar')
                    ->visible(fn() => in_array('size', $this->selectedGroups)),

                SelectColumn::make('production_category')
                    ->label('Kategori')
                    ->options($categoryOptions)
                    ->sortable(),

                SelectColumn::make('bahan_id')
                    ->label('Bahan')
                    ->options($bahanOptions)
                    ->sortable(),


                TextInputColumn::make('price')
                    ->label('Harga')
                    ->sortable()
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(function () {
                        $this->refreshOrderData();
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('dynamic_group')
                    ->label('')
                    ->column('dynamic_group')
                    ->getTitleFromRecordUsing(function (OrderItem $record): string {
                        $labels = [];

                        if (in_array('product_name', $this->selectedGroups)) {
                            $labels[] = "📦 " . ($record->product_name ?: 'Produk');
                        }

                        if (in_array('category', $this->selectedGroups)) {
                            $cat = $record->production_category === 'custom' ? 'produksi' : $record->production_category;
                            $labels[] = match ($cat) {
                                'produksi' => '🏭 Konveksi',
                                'non_produksi' => '📦 Baju Jadi',
                                'jasa' => '🔧 Jasa',
                                default => $cat,
                            };
                        }

                        if (in_array('gender', $this->selectedGroups)) {
                            $gender = $record->size_and_request_details['gender'] ?? 'L';
                            $labels[] = $gender === 'P' ? '👩 Perempuan' : '👨 Laki-laki';
                        }

                        if (in_array('bahan', $this->selectedGroups)) {
                            $labels[] = "🧶 " . ($record->bahan_name ?: 'Bahan');
                        }

                        if (in_array('size', $this->selectedGroups)) {
                            $labels[] = "📏 " . ($record->size ?: '-');
                        }

                        if (in_array('recipient', $this->selectedGroups)) {
                            $labels[] = "👤 " . ($record->recipient_name ?: '-');
                        }

                        return implode('  /  ', $labels);
                    })
                    ->collapsible()
            )
            ->headerActions([
                Action::make('configure_grouping')
                    ->label('Pengaturan Grouping')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\CheckboxList::make('groups')
                            ->label('Pilih Kriteria Grouping (Hirarki)')
                            ->options([
                                'product_name' => 'Nama Produk/Item',
                                'category' => 'Kategori Pesanan',
                                'gender' => 'Jenis Kelamin',
                                'bahan' => 'Bahan / Kain',
                                'size' => 'Ukuran (Size)',
                                'recipient' => 'Penerima',
                            ])
                            ->default($this->selectedGroups)
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $this->selectedGroups = $data['groups'];
                    }),
                Action::make('bulk_generate')
                    ->label('Bulk Generate / Tambah Item')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions, $genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions, $tenantId) {
                        return [
                            Section::make('Kategori & Dasar')->schema([
                                Grid::make(3)->schema([
                                    Select::make('bulk_category')
                                        ->label('Pilih Kategori')
                                        ->options($categoryOptions)
                                        ->default('produksi')
                                        ->required()
                                        ->live(),
                                    TextInput::make('bulk_product_name')
                                        ->label('Nama Produk/Jasa')
                                        ->datalist(function () use ($tenantId) {
                                            return OrderItem::whereHas('order', fn($q) => $q->where('shop_id', $tenantId))
                                                ->distinct()
                                                ->pluck('product_name')
                                                ->toArray();
                                        })
                                        ->required()
                                        ->placeholder('Pilih atau ketik nama baru...')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if (!$state)
                                                return;

                                            // Cari item terakhir dengan nama produk yang sama di pesanan ini
                                            $existing = OrderItem::where('order_id', $this->order->id)
                                                ->where('product_name', $state)
                                                ->latest('id')
                                                ->first();

                                            if ($existing) {
                                                $details = $existing->size_and_request_details ?? [];

                                                $set('bulk_category', $existing->production_category === 'custom' ? 'produksi' : ($existing->production_category ?? 'produksi'));
                                                $set('bulk_bahan', $existing->bahan_id);
                                                $set('bulk_material_variant_id', $details['material_variant_id'] ?? null);

                                                $set('bulk_gender', $details['gender'] ?? 'L');
                                                $set('bulk_sleeve', $details['sleeve_model'] ?? 'pendek');
                                                $set('bulk_pocket', $details['pocket_model'] ?? 'tanpa_saku');
                                                $set('bulk_button', $details['button_model'] ?? 'biasa');
                                                $set('bulk_is_tunic', (bool) ($details['is_tunic'] ?? false));

                                                // Salin Sablon/Bordir
                                                $sb = $details['sablon_bordir'][0] ?? null;
                                                if ($sb) {
                                                    $set('bulk_sablon_teknik', $sb['jenis'] ?? null);
                                                    $set('bulk_sablon_lokasi', $sb['lokasi'] ?? null);
                                                }

                                                Notification::make()
                                                    ->title("Spesifikasi '{$state}' berhasil disalin!")
                                                    ->success()
                                                    ->send();
                                            }
                                        })
                                        ->columnSpan(2),
                                ]),
                            ]),

                            // MODE 1: KONVEKSI
                            Section::make('Detail Konveksi')->schema([
                                Grid::make(2)->schema([
                                    Select::make('bulk_bahan')
                                        ->label('Bahan')
                                        ->options($bahanOptions)
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(fn(Set $set) => $set('bulk_material_variant_id', null)),
                                    Select::make('bulk_material_variant_id')
                                        ->id('bulk_material_variant_id')
                                        ->label('Warna / Varian')
                                        ->allowHtml()
                                        ->options(function (Get $get) {
                                            $bahanId = $get('bulk_bahan');
                                            if (!$bahanId)
                                                return [];
                                            return \App\Models\MaterialVariant::where('material_id', $bahanId)
                                                ->get()
                                                ->mapWithKeys(fn($v) => [
                                                    $v->id => "<div class='flex items-center gap-2'><div class='w-4 h-4 rounded-full border border-gray-300' style='background-color: {$v->color_code}'></div> {$v->color_name}</div>"
                                                ]);
                                        })
                                        ->searchable()
                                        ->placeholder('Pilih warna...')
                                        ->visible(fn(Get $get) => filled($get('bulk_bahan'))),
                                ]),
                                Group::make([
                                    Grid::make(2)->schema([
                                        Select::make('bulk_sablon_teknik')
                                            ->label('Teknik Sablon / Bordir')
                                            ->options(\App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                            ->searchable(),
                                        Select::make('bulk_sablon_lokasi')
                                            ->label('Posisi / Lokasi')
                                            ->options(\App\Filament\Resources\Orders\OrderResource::$lokasiSablonOptions)
                                            ->searchable(),
                                    ]),
                                ])
                                    ->extraAttributes([
                                        'class' => 'p-4 bg-indigo-50/40 rounded-xl',
                                        'style' => 'border: 1px solid #e2e8f0'
                                    ]),

                                Group::make([
                                    Grid::make(3)->schema([
                                        Select::make('bulk_gender')
                                            ->label('Gender')
                                            ->options($genderOptions)
                                            ->default('L'),
                                        Select::make('bulk_sleeve')
                                            ->label('Model Lengan')
                                            ->options($sleeveOptions)
                                            ->default('pendek'),
                                        Select::make('bulk_pocket')
                                            ->label('Model Saku')
                                            ->options($pocketOptions)
                                            ->default('tanpa_saku'),
                                    ]),
                                    Grid::make(3)->schema([
                                        Select::make('bulk_button')
                                            ->label('Model Kancing')
                                            ->options($buttonOptions)
                                            ->default('biasa'),
                                        Toggle::make('bulk_is_tunic')
                                            ->label('Tunik / Gamis?')
                                            ->default(false)
                                            ->inline(false)
                                    ]),
                                ])
                                    ->extraAttributes([
                                        'class' => 'p-4 bg-amber-50/40 rounded-xl',
                                        'style' => 'border: 1px solid #e2e8f0'
                                    ]),

                            ])
                                ->visible(fn(Get $get) => $get('bulk_category') === 'produksi'),

                            // MODE 2: BAJU JADI
                            Section::make('Detail Baju Jadi')->schema([
                                Grid::make(2)->schema([
                                    Select::make('bulk_product_id')
                                        ->label('Pilih Produk Supplier')
                                        ->options(Product::where('shop_id', $tenantId)->pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            $product = Product::find($state);
                                            if ($product)
                                                $set('bulk_product_name', $product->name);
                                        }),
                                    TextInput::make('bulk_price_non')
                                        ->label('Harga Jual')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0),
                                ]),
                            ])
                                ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi'),

                            // MODE 3: JASA
                            Section::make('Detail Jasa')->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('bulk_price_jasa')
                                        ->label('Harga Jasa')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0),
                                    TextInput::make('bulk_qty_jasa')
                                        ->label('Jumlah (Qty)')
                                        ->numeric()
                                        ->default(1),
                                ]),
                            ])
                                ->visible(fn(Get $get) => $get('bulk_category') === 'jasa'),

                            // QUANTITIES (For Produksi & Baju Jadi)
                            Section::make('Jumlah per Ukuran')->schema([
                                Grid::make(6)->schema(function () use ($sizeOptions) {
                                    $sizeFields = [];
                                    foreach ($sizeOptions as $key => $label) {
                                        $sizeFields[] = TextInput::make("qty_{$key}")
                                            ->label($label)
                                            ->numeric()
                                            ->default(0);
                                    }
                                    $sizeFields[] = TextInput::make('qty_custom')
                                        ->label('Custom')
                                        ->numeric()
                                        ->default(0)
                                        ->live();
                                    return $sizeFields;
                                }),
                            ])
                                ->visible(fn(Get $get) => in_array($get('bulk_category'), ['produksi', 'non_produksi'])),

                            Section::make('Harga Satuan')->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('bulk_price')
                                        ->label('Harga per Pcs')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0),
                                    TextInput::make('bulk_price_custom')
                                        ->label('Harga Custom per Pcs')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->visible(fn(Get $get) => (int) $get('qty_custom') > 0),
                                ]),
                            ])
                                ->extraAttributes([
                                    'class' => 'bg-emerald-50/40 rounded-xl',
                                    'style' => 'border: 1px solid #e2e8f0'
                                ])
                                ->visible(fn(Get $get) => $get('bulk_category') === 'produksi'),
                        ];
                    })
                    ->modalWidth('5xl')
                    ->modalHeading('Bulk Generate / Tambah Produk')
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
                            $price = (int) ($category === 'produksi' ? ($data['bulk_price'] ?? 0) : ($data['bulk_price_non'] ?? 0));
                            $bahanId = $data['bulk_bahan'] ?? null;
                            $productId = $data['bulk_product_id'] ?? null;

                            $details = [];
                            if ($category === 'produksi') {
                                $details = [
                                    'gender' => $data['bulk_gender'] ?? 'L',
                                    'sleeve_model' => $data['bulk_sleeve'] ?? 'pendek',
                                    'pocket_model' => $data['bulk_pocket'] ?? 'tanpa_saku',
                                    'button_model' => $data['bulk_button'] ?? 'biasa',
                                    'is_tunic' => (bool) ($data['bulk_is_tunic'] ?? false),
                                    'sablon_bordir' => !empty($data['bulk_sablon_teknik']) ? [
                                        [
                                            'jenis' => $data['bulk_sablon_teknik'],
                                            'lokasi' => $data['bulk_sablon_lokasi'] ?? '-',
                                        ]
                                    ] : [],
                                ];
                            }

                            $items = [];
                            foreach ($sizeOptions as $key => $label) {
                                $qty = (int) ($data["qty_{$key}"] ?? 0);
                                if ($qty > 0) {
                                    $itemPrice = ($category === 'produksi' ? (int) ($data['bulk_price'] ?? 0) : (int) ($data['bulk_price_non'] ?? 0));
                                    for ($i = 0; $i < $qty; $i++) {
                                        $items[] = [
                                            'product_name' => $productName,
                                            'size' => $key,
                                            'production_category' => $category,
                                            'bahan_id' => $bahanId,
                                            'product_id' => $productId,
                                            'price' => $itemPrice,
                                            'quantity' => 1,
                                            'size_and_request_details' => array_merge($details, [
                                                'material_variant_id' => $data['bulk_material_variant_id'] ?? null,
                                            ]),
                                        ];
                                    }
                                }
                            }

                            $customQty = (int) ($data['qty_custom'] ?? 0);
                            if ($customQty > 0) {
                                $customPrice = ($category === 'produksi' ? (int) ($data['bulk_price_custom'] ?? $data['bulk_price'] ?? 0) : (int) ($data['bulk_price_non'] ?? 0));
                                for ($i = 0; $i < $customQty; $i++) {
                                    $items[] = [
                                        'product_name' => $productName,
                                        'size' => 'Custom',
                                        'production_category' => $category,
                                        'bahan_id' => $bahanId,
                                        'product_id' => $productId,
                                        'price' => $customPrice,
                                        'quantity' => 1,
                                        'size_and_request_details' => array_merge($details, [
                                            'material_variant_id' => $data['bulk_material_variant_id'] ?? null,
                                        ]),
                                    ];
                                }
                            }

                            foreach ($items as $item) {
                                $this->order->orderItems()->create($item);
                            }
                        }

                        $this->refreshOrderData();
                        Notification::make()->success()->title('Item berhasil ditambahkan!')->send();
                    }),
            ])
            ->actions([
                Action::make('edit_specs')
                    ->label('Detail')
                    ->icon('heroicon-m-adjustments-vertical')
                    ->modalHeading('Detail & Ukuran Badan')
                    ->form([
                        Grid::make(2)->schema([
                            Select::make('bahan_id')
                                ->label('Bahan')
                                ->options($bahanOptions)
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('material_variant_id', null)),

                            Select::make('material_variant_id')
                                ->id('material_variant_id')
                                ->label('Warna / Varian')
                                ->allowHtml()
                                ->options(function (Get $get) {
                                    $bahanId = $get('bahan_id');
                                    if (!$bahanId)
                                        return [];
                                    return \App\Models\MaterialVariant::where('material_id', $bahanId)
                                        ->get()
                                        ->mapWithKeys(fn($v) => [
                                            $v->id => "<div class='flex items-center gap-2'><div class='w-4 h-4 rounded-full border border-gray-300' style='background-color: {$v->color_code}'></div> {$v->color_name}</div>"
                                        ]);
                                })
                                ->searchable()
                                ->placeholder('Pilih warna...')
                                ->visible(fn(Get $get) => filled($get('bahan_id'))),
                        ]),

                        Group::make([
                            Grid::make(2)->schema([
                                Select::make('sablon_teknik')
                                    ->label('Teknik Sablon / Bordir')
                                    ->options(\App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                    ->searchable(),
                                Select::make('sablon_lokasi')
                                    ->label('Posisi / Lokasi')
                                    ->options(\App\Filament\Resources\Orders\OrderResource::$lokasiSablonOptions)
                                    ->searchable(),
                            ]),
                        ])
                            ->extraAttributes([
                                'class' => 'p-4 bg-indigo-50/40 rounded-xl',
                                'style' => 'border: 1px solid #e2e8f0'
                            ]),

                        Group::make([
                            Grid::make(3)->schema([
                                Select::make('gender')
                                    ->label('Gender')
                                    ->options($genderOptions),
                                Select::make('sleeve_model')
                                    ->label('Model Lengan')
                                    ->options($sleeveOptions),
                                Select::make('pocket_model')
                                    ->label('Model Saku')
                                    ->options($pocketOptions),
                            ]),
                            Grid::make(3)->schema([
                                Select::make('button_model')
                                    ->label('Model Kancing')
                                    ->options($buttonOptions),
                                Toggle::make('is_tunic')
                                    ->label('Tunik / Gamis?')
                                    ->inline(false)
                            ]),
                        ])
                            ->extraAttributes([
                                'class' => 'p-4 bg-amber-50/40 rounded-xl',
                                'style' => 'border: 1px solid #e2e8f0'
                            ]),

                        Section::make('Harga Satuan')->schema([
                            TextInput::make('price')
                                ->label('Harga Satuan')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),
                        ])
                            ->extraAttributes([
                                'class' => 'bg-emerald-50/40 rounded-xl',
                                'style' => 'border: 1px solid #e2e8f0'
                            ]),
                    ])
                    ->fillForm(function (OrderItem $record) {
                        $data = $record->size_and_request_details ?? [];
                        $data['bahan_id'] = $record->bahan_id;
                        $data['price'] = $record->price;

                        // Map back for form
                        $sb = $data['sablon_bordir'][0] ?? null;
                        if ($sb) {
                            $data['sablon_teknik'] = $sb['jenis'] ?? null;
                            $data['sablon_lokasi'] = $sb['lokasi'] ?? null;
                        }

                        return $data;
                    })
                    ->action(function (OrderItem $record, array $data): void {
                        $bahanId = $data['bahan_id'] ?? null;
                        $price = $data['price'] ?? 0;

                        // Map to JSON structure
                        $data['sablon_bordir'] = !empty($data['sablon_teknik']) ? [
                            [
                                'jenis' => $data['sablon_teknik'],
                                'lokasi' => $data['sablon_lokasi'] ?? '-',
                            ]
                        ] : [];

                        unset($data['bahan_id'], $data['price'], $data['sablon_teknik'], $data['sablon_lokasi']);

                        $existingDetails = $record->size_and_request_details ?? [];

                        $record->update([
                            'bahan_id' => $bahanId,
                            'price' => $price,
                            'size_and_request_details' => array_merge($existingDetails, $data),
                        ]);

                        Notification::make()->success()->title('Spesifikasi diperbarui')->send();
                        $this->refreshOrderData();
                    }),
                Action::make('edit_measurements')
                    ->label('Ukur')
                    ->icon('heroicon-m-viewfinder-circle')
                    ->color('warning')
                    ->modalSubmitAction(fn ($action) => $action->color('primary'))
                    ->modalHeading('Rekam Ukuran Badan (cm)')
                    ->modalWidth('2xl')
                    ->visible(fn(OrderItem $record) => $record->size === 'Custom')
                    ->form([
                        Grid::make(3)->schema([
                            TextInput::make('LD')->label('LD (L. Dada)')->numeric()->suffix('cm'),
                            TextInput::make('PB')->label('PB (P. Baju)')->numeric()->suffix('cm'),
                            TextInput::make('PL')->label('PL (P. Lengan)')->numeric()->suffix('cm'),
                            TextInput::make('LB')->label('LB (L. Bahu)')->numeric()->suffix('cm'),
                            TextInput::make('LP')->label('LP (L. Perut)')->numeric()->suffix('cm'),
                            TextInput::make('LPh')->label('LPh (L. Paha)')->numeric()->suffix('cm'),
                        ]),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Catatan/Instruksi Khusus (Per-Orang)')
                            ->placeholder('Contoh: Celana pakai karet, Lengan pakai kancing bungkus, dll.')
                            ->rows(2),
                    ])
                    ->fillForm(fn(OrderItem $record) => $record->size_and_request_details ?? [])
                    ->action(function (OrderItem $record, array $data): void {
                        $details = $record->size_and_request_details ?? [];
                        $record->update([
                            'size_and_request_details' => array_merge($details, $data),
                        ]);
                        Notification::make()->success()->title('Ukuran badan berhasil disimpan')->send();
                    }),
                DeleteAction::make()
                    ->after(fn() => $this->refreshOrderData()),
            ])
            ->bulkActions([
                BulkAction::make('bulk_edit_price')
                    ->label('Ubah Harga')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        TextInput::make('price')
                            ->label('Harga Baru')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['price' => (int) $data['price']]);
                        $this->refreshOrderData();
                    }),
                BulkAction::make('bulk_edit_specs')
                    ->label('Ubah Atribut')
                    ->icon('heroicon-m-adjustments-vertical')
                    ->modalHeading('Ubah Atribut Sekaligus')
                    ->form(function () use ($bahanOptions, $genderOptions, $sleeveOptions, $pocketOptions, $buttonOptions) {
                        return [
                            Grid::make(2)->schema([
                                Select::make('bahan_id')
                                    ->label('Bahan (Biarkan kosong jika tidak ingin diubah)')
                                    ->options($bahanOptions)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('material_variant_id', null)),

                                Select::make('material_variant_id')
                                    ->id('bulk_edit_material_variant_id')
                                    ->label('Warna / Varian')
                                    ->allowHtml()
                                    ->options(function (Get $get) {
                                        $bahanId = $get('bahan_id');
                                        if (!$bahanId)
                                            return [];
                                        return \App\Models\MaterialVariant::where('material_id', $bahanId)
                                            ->get()
                                            ->mapWithKeys(fn($v) => [
                                                $v->id => "<div class='flex items-center gap-2'><div class='w-4 h-4 rounded-full border border-gray-300' style='background-color: {$v->color_code}'></div> {$v->color_name}</div>"
                                            ]);
                                    })
                                    ->searchable()
                                    ->placeholder('Pilih warna...')
                                    ->visible(fn(Get $get) => filled($get('bahan_id'))),
                            ]),

                            Group::make([
                                Grid::make(2)->schema([
                                    Select::make('sablon_teknik')
                                        ->label('Teknik Sablon / Bordir (Massal)')
                                        ->options(\App\Models\PrintType::where('category', 'jenis')->pluck('name', 'name'))
                                        ->searchable(),
                                    Select::make('sablon_lokasi')
                                        ->label('Posisi / Lokasi (Massal)')
                                        ->options(\App\Filament\Resources\Orders\OrderResource::$lokasiSablonOptions)
                                        ->searchable(),
                                ]),
                            ])
                                ->extraAttributes([
                                    'class' => 'p-4 bg-indigo-50/40 rounded-xl',
                                    'style' => 'border: 1px solid #e2e8f0'
                                ]),

                            Group::make([
                                Grid::make(3)->schema([
                                    Select::make('gender')
                                        ->label('Gender')
                                        ->options($genderOptions)
                                        ->placeholder('Pilih...'),
                                    Select::make('sleeve_model')
                                        ->label('Model Lengan')
                                        ->options($sleeveOptions)
                                        ->placeholder('Pilih...'),
                                    Select::make('pocket_model')
                                        ->label('Model Saku')
                                        ->options($pocketOptions)
                                        ->placeholder('Pilih...'),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('button_model')
                                        ->label('Model Kancing')
                                        ->options($buttonOptions)
                                        ->placeholder('Pilih...'),
                                    Select::make('is_tunic')
                                        ->label('Tunik / Gamis?')
                                        ->options([
                                            '1' => 'Ya (Tunik)',
                                            '0' => 'Bukan',
                                        ])
                                        ->placeholder('Biarkan aslinya...'),
                                ]),
                            ])
                                ->extraAttributes([
                                    'class' => 'p-4 bg-amber-50/40 rounded-xl',
                                    'style' => 'border: 1px solid #e2e8f0'
                                ]),
                        ];
                    })
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $updateData = [];

                            if (isset($data['bahan_id']) && filled($data['bahan_id'])) {
                                $updateData['bahan_id'] = $data['bahan_id'];
                            }

                            $details = $record->size_and_request_details ?? [];

                            $fieldsToSync = ['material_variant_id', 'gender', 'sleeve_model', 'pocket_model', 'button_model'];
                            foreach ($fieldsToSync as $field) {
                                if (isset($data[$field]) && filled($data[$field])) {
                                    $details[$field] = $data[$field];
                                }
                            }

                            if (isset($data['sablon_teknik']) && filled($data['sablon_teknik'])) {
                                $details['sablon_bordir'] = [
                                    [
                                        'jenis' => $data['sablon_teknik'],
                                        'lokasi' => $data['sablon_lokasi'] ?? '-',
                                    ]
                                ];
                            }

                            if (isset($data['is_tunic']) && filled($data['is_tunic'])) {
                                $details['is_tunic'] = (bool) $data['is_tunic'];
                            }

                            $record->update(array_merge($updateData, [
                                'size_and_request_details' => $details
                            ]));
                        }
                        Notification::make()->success()->title('Atribut berhasil diubah massal')->send();
                        $this->refreshOrderData();
                    }),
                DeleteBulkAction::make()
                    ->after(fn() => $this->refreshOrderData()),
            ])
            ->paginated(false);
    }

    protected function refreshOrderData(): void
    {
        // Sum prices directly since quantity is always 1 per row
        $subtotal = $this->order->orderItems()->sum('price');
        $this->order->update(['subtotal' => (int) $subtotal]);

        $this->dispatch('refreshOrderSummary', subtotal: (int) $subtotal);
    }

    public function render()
    {
        return view('livewire.integrated-order-items-table');
    }
}
