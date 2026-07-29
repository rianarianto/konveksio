<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderItem;
use App\Models\Material;
use App\Models\StoreSize;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

use Filament\Support\Icons\Heroicon;

class OrderItemRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    protected static ?string $title = 'Daftar Item Pesanan';

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

        return $table
            ->columns([
                TextInputColumn::make('product_name')
                    ->label('Nama / Item')
                    ->placeholder('Nama produk...')
                    ->searchable()
                    ->rules(['required', 'max:255']),

                TextInputColumn::make('recipient_name')
                    ->label('Penerima')
                    ->placeholder('Nama penerima...')
                    ->searchable(),

                SelectColumn::make('size')
                    ->label('Size')
                    ->options(array_merge($sizeOptions, ['Custom' => 'Custom']))
                    ->sortable(),

                TextColumn::make('specs')
                    ->label('JK / Lengan / Saku')
                    ->state(function (OrderItem $record) {
                        $gender = $record->gender === 'L' ? '♂️ L' : '♀️ P';
                        $lengan = Str::title($record->sleeve_model);
                        $saku = $record->pocket_model === 'tanpa_saku' ? '❌ Saku' : '✅ ' . Str::title($record->pocket_model);
                        return "{$gender} | {$lengan} | {$saku}";
                    })
                    ->description(fn(OrderItem $record) => $record->production_category === 'produksi' ? '🧵 ' . ($record->bahan?->name ?? 'Bahan Belum Diatur') : '📦 Non-Produksi / Jasa')
                    ->color('gray')
                    ->fontFamily('mono')
                    ->size('xs'),

                TextInputColumn::make('price')
                    ->label('Harga')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->afterStateUpdated(function () {
                        $this->updateOrderSubtotal();
                    }),

                TextInputColumn::make('quantity')
                    ->label('Qty')
                    ->rules(['required', 'numeric', 'min:1'])
                    ->afterStateUpdated(function () {
                        $this->updateOrderSubtotal();
                    }),

                TextColumn::make('subtotal')
                    ->label('Total')
                    ->state(fn(OrderItem $record) => $record->price * $record->quantity)
                    ->money('IDR')
                    ->color('primary')
                    ->weight('bold')
                    ->alignEnd(),
            ])

            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('Tambah Item')
                    ->icon('heroicon-o-plus')
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions) {
                        $order = $this->getOwnerRecord();
                        $existingProductNames = $order->orderItems()
                            ->pluck('product_name', 'product_name')
                            ->filter()
                            ->unique()
                            ->toArray();

                        return [
                            \Filament\Forms\Components\Grid::make(2)
                                ->schema([
                                    Select::make('product_name')
                                        ->label('Nama Item / Produk')
                                        ->options($existingProductNames)
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('new_product_name')
                                                ->label('Nama Produk Baru')
                                                ->required(),
                                        ])
                                        ->createOptionUsing(fn (array $data) => $data['new_product_name'])
                                        ->required()
                                        ->reactive()
                                        ->columnSpanFull()
                                        ->afterStateUpdated(function ($state, callable $set) use ($order) {
                                            if (!$state) return;
                                            $existingItem = $order->orderItems()->where('product_name', $state)->first();
                                            if ($existingItem) {
                                                $set('production_category', $existingItem->production_category);
                                                $set('bahan_id', $existingItem->bahan_id);
                                                $set('price', $existingItem->price);
                                            }
                                        }),

                                    Select::make('size')
                                        ->label('Ukuran')
                                        ->options(array_merge($sizeOptions, ['Custom' => 'Custom']))
                                        ->searchable()
                                        ->required(),

                                    TextInput::make('recipient_name')
                                        ->label('Nama Penerima (Opsional)')
                                        ->placeholder('Contoh: Andre / Budi'),

                                    Select::make('production_category')
                                        ->label('Kategori')
                                        ->options($categoryOptions)
                                        ->required()
                                        ->default('produksi')
                                        ->disabled(fn ($get) => !empty($get('product_name')) && $order->orderItems()->where('product_name', $get('product_name'))->exists())
                                        ->dehydrated(),

                                    Select::make('bahan_id')
                                        ->label('Bahan')
                                        ->options($bahanOptions)
                                        ->searchable()
                                        ->disabled(fn ($get) => !empty($get('product_name')) && $order->orderItems()->where('product_name', $get('product_name'))->exists())
                                        ->dehydrated(),

                                    TextInput::make('price')
                                        ->label('Harga Satuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required()
                                        ->default(0),

                                    TextInput::make('quantity')
                                        ->label('Quantity (Pcs)')
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->minValue(1),
                                ])
                        ];
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $order = $this->getOwnerRecord();
                        $existingItem = $order->orderItems()->where('product_name', $data['product_name'] ?? '')->first();

                        if ($existingItem) {
                            $data['production_category'] = $existingItem->production_category;
                            $data['bahan_id'] = $existingItem->bahan_id;
                            $data['size_and_request_details'] = $existingItem->size_and_request_details;
                            $data['design_status'] = $existingItem->design_status;
                            $data['design_image'] = $existingItem->design_image;
                            $data['is_addition'] = true;
                        }

                        return $data;
                    })
                    ->after(function () {
                        $this->updateOrderSubtotal();
                    }),

                // ─── BULK GENERATOR ────────────────────────────────────────
                \Filament\Actions\Action::make('bulk_generate')
                    ->label('Bulk Generate')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions) {
                        $order = $this->getOwnerRecord();
                        $existingProductNames = $order->orderItems()
                            ->pluck('product_name', 'product_name')
                            ->filter()
                            ->unique()
                            ->toArray();

                        return [
                            Select::make('bulk_product_name')
                                ->label('Nama Item / Produk')
                                ->options($existingProductNames)
                                ->searchable()
                                ->createOptionForm([
                                    TextInput::make('new_product_name')
                                        ->label('Nama Produk Baru')
                                        ->required(),
                                ])
                                ->createOptionUsing(fn (array $data) => $data['new_product_name'])
                                ->required()
                                ->reactive()
                                ->columnSpanFull()
                                ->afterStateUpdated(function ($state, callable $set) use ($order) {
                                    if (!$state) return;
                                    $existingItem = $order->orderItems()->where('product_name', $state)->first();
                                    if ($existingItem) {
                                        $set('bulk_category', $existingItem->production_category);
                                        $set('bulk_bahan', $existingItem->bahan_id);
                                        $set('bulk_price', $existingItem->price);
                                    }
                                }),

                            \Filament\Forms\Components\Grid::make(3)
                                ->schema([
                                    Select::make('bulk_category')
                                        ->label('Kategori')
                                        ->options($categoryOptions)
                                        ->default('produksi')
                                        ->required()
                                        ->disabled(fn ($get) => !empty($get('bulk_product_name')) && $order->orderItems()->where('product_name', $get('bulk_product_name'))->exists())
                                        ->dehydrated(),

                                    Select::make('bulk_bahan')
                                        ->label('Bahan')
                                        ->options($bahanOptions)
                                        ->searchable()
                                        ->disabled(fn ($get) => !empty($get('bulk_product_name')) && $order->orderItems()->where('product_name', $get('bulk_product_name'))->exists())
                                        ->dehydrated(),

                                    TextInput::make('bulk_price')
                                        ->label('Harga Default')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0),
                                ]),
                            
                            \Filament\Forms\Components\Section::make('Jumlah per Ukuran')
                                ->description('Masukkan jumlah pesanan untuk setiap ukuran.')
                                ->schema([
                                    \Filament\Forms\Components\Grid::make(4)
                                        ->schema([
                                            ...collect($sizeOptions)->map(function ($label, $key) {
                                                return TextInput::make("qty_{$key}")
                                                    ->label($label)
                                                    ->numeric()
                                                    ->default(0)
                                                    ->extraInputAttributes(['onclick' => 'this.select()']);
                                            })->values()->toArray(),
                                            
                                            TextInput::make('qty_custom')
                                                ->label('Custom')
                                                ->numeric()
                                                ->default(0)
                                                ->live()
                                                ->extraInputAttributes(['onclick' => 'this.select()']),

                                            TextInput::make('bulk_price_custom')
                                                ->label('Harga Khusus Custom')
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->placeholder(fn ($get) => $get('bulk_price') ?: '0')
                                                ->helperText('Harga khusus untuk item ukuran Custom')
                                                ->visible(fn ($get) => (int) ($get('qty_custom') ?? 0) > 0),
                                        ])
                                ])
                                ->compact()
                        ];
                    })
                    ->modalWidth('lg')
                    ->modalHeading('Bulk Generate Items')
                    ->modalDescription('Masukkan jumlah per ukuran. Item akan langsung ditambahkan ke tabel.')
                    ->action(function (array $data) use ($sizeOptions) {
                        $order = $this->getOwnerRecord();
                        $productName = $data['bulk_product_name'] ?? 'Item Baru';
                        $existingItem = $order->orderItems()->where('product_name', $productName)->first();

                        $category = $data['bulk_category'] ?? ($existingItem?->production_category ?? 'produksi');
                        $bahanId = $data['bulk_bahan'] ?? ($existingItem?->bahan_id ?? null);
                        $price = (int) ($data['bulk_price'] ?? ($existingItem?->price ?? 0));
                        $rawItems = [];

                        foreach ($sizeOptions as $key => $label) {
                            $qty = (int) ($data["qty_{$key}"] ?? 0);
                            for ($i = 0; $i < $qty; $i++) {
                                $rawItems[] = [
                                    'size' => $key,
                                    'price' => $price,
                                ];
                            }
                        }

                        $customQty = (int) ($data['qty_custom'] ?? 0);
                        $customPrice = (int) ($data['bulk_price_custom'] ?? $price);

                        for ($i = 0; $i < $customQty; $i++) {
                            $rawItems[] = [
                                'size' => 'Custom',
                                'price' => $customPrice,
                            ];
                        }

                        if (empty($rawItems)) {
                            Notification::make()
                                ->warning()
                                ->title('Tidak ada item yang digenerate')
                                ->body('Masukkan minimal 1 qty pada salah satu ukuran.')
                                ->send();
                            return;
                        }

                        foreach ($rawItems as $itemData) {
                            $newItemData = [
                                'product_name' => $productName,
                                'size' => $itemData['size'],
                                'production_category' => $category,
                                'bahan_id' => $bahanId,
                                'price' => $itemData['price'],
                                'quantity' => 1,
                            ];

                            if ($existingItem) {
                                $newItemData['size_and_request_details'] = $existingItem->size_and_request_details;
                                $newItemData['design_status'] = $existingItem->design_status;
                                $newItemData['design_image'] = $existingItem->design_image;
                                $newItemData['is_addition'] = true;
                            }

                            $order->orderItems()->create($newItemData);
                        }

                        $this->updateOrderSubtotal();

                        Notification::make()
                            ->success()
                            ->title(count($rawItems) . ' item berhasil ditambahkan!')
                            ->send();
                    }),

                // ─── SINKRONKAN SPESIFIKASI ──────────────────────────────────
                \Filament\Actions\Action::make('sync_specs')
                    ->label('Sinkronkan Spesifikasi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Sinkronkan Spesifikasi Item Pesanan')
                    ->modalDescription('Tindakan ini akan menyeragamkan spesifikasi (bahan, lengan, kerah, saku, kancing) seluruh item yang memiliki nama produk yang sama.')
                    ->action(function () {
                        $order = $this->getOwnerRecord();
                        $grouped = $order->orderItems()->get()->groupBy('product_name');
                        $updatedCount = 0;

                        foreach ($grouped as $pName => $items) {
                            if ($items->count() <= 1) continue;

                            $primaryL = $items->first(fn($i) => strtoupper($i->size_and_request_details['gender'] ?? 'L') === 'L');
                            $primaryP = $items->first(fn($i) => strtoupper($i->size_and_request_details['gender'] ?? 'L') === 'P');
                            $baseItem = $items->first();

                            foreach ($items as $item) {
                                $gender = strtoupper($item->size_and_request_details['gender'] ?? 'L');
                                $refItem = ($gender === 'P' && $primaryP) ? $primaryP : (($gender === 'L' && $primaryL) ? $primaryL : $baseItem);

                                $updateData = [
                                    'bahan_id' => $refItem->bahan_id,
                                    'production_category' => $refItem->production_category,
                                    'design_status' => $refItem->design_status,
                                    'design_image' => $refItem->design_image,
                                ];

                                if ($refItem->id !== $item->id) {
                                    $details = $item->size_and_request_details ?? [];
                                    $refDetails = $refItem->size_and_request_details ?? [];

                                    $details['material_variant_id'] = $refDetails['material_variant_id'] ?? ($details['material_variant_id'] ?? null);
                                    $details['sleeve_model'] = $refDetails['sleeve_model'] ?? ($details['sleeve_model'] ?? null);
                                    $details['pocket_model'] = $refDetails['pocket_model'] ?? ($details['pocket_model'] ?? null);
                                    $details['button_model'] = $refDetails['button_model'] ?? ($details['button_model'] ?? null);
                                    $details['collar_model'] = $refDetails['collar_model'] ?? ($details['collar_model'] ?? null);
                                    if ($gender === 'P' && isset($refDetails['model'])) {
                                        $details['model'] = $refDetails['model'];
                                    }

                                    $updateData['size_and_request_details'] = $details;
                                    $item->update($updateData);
                                    $updatedCount++;
                                }
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title("Spesifikasi berhasil disinkronkan!")
                            ->body("{$updatedCount} item telah diseragamkan spesifikasinya.")
                            ->send();
                    }),
            ])

            ->actions([
                \Filament\Actions\Action::make('edit_specs')
                    ->label('Detail')
                    ->icon('heroicon-m-adjustments-vertical')
                    ->modalHeading('Detail Spesifikasi & Ukuran')
                    ->modalSubmitActionLabel('Simpan')
                    ->form([
                        Select::make('gender')
                            ->label('Gender')
                            ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                        Select::make('sleeve_model')
                            ->label('Model Lengan')
                            ->options(['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4']),
                        Select::make('pocket_model')
                            ->label('Model Saku')
                            ->options(['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok']),
                        Select::make('button_model')
                            ->label('Model Kancing')
                            ->options(['biasa' => 'Biasa', 'snap' => 'Snap/Tertutup']),
                        TextInput::make('LD')->label('LD (L. Dada)')->numeric()->suffix('cm'),
                        TextInput::make('PB')->label('PB (P. Baju)')->numeric()->suffix('cm'),
                        TextInput::make('PL')->label('PL (P. Lengan)')->numeric()->suffix('cm'),
                        TextInput::make('LB')->label('LB (L. Bahu)')->numeric()->suffix('cm'),
                        TextInput::make('LP')->label('LP (L. Perut)')->numeric()->suffix('cm'),
                        TextInput::make('LPh')->label('LPh (L. Paha)')->numeric()->suffix('cm'),
                    ])
                    ->fillForm(function (OrderItem $record): array {
                        $details = $record->size_and_request_details ?? [];
                        return [
                            'gender' => $details['gender'] ?? null,
                            'sleeve_model' => $details['sleeve_model'] ?? null,
                            'pocket_model' => $details['pocket_model'] ?? null,
                            'button_model' => $details['button_model'] ?? null,
                            'LD' => $details['LD'] ?? null,
                            'PB' => $details['PB'] ?? null,
                            'PL' => $details['PL'] ?? null,
                            'LB' => $details['LB'] ?? null,
                            'LP' => $details['LP'] ?? null,
                            'LPh' => $details['LPh'] ?? null,
                        ];
                    })
                    ->action(function (OrderItem $record, array $data): void {
                        $details = $record->size_and_request_details ?? [];
                        $details = array_merge($details, [
                            'gender' => $data['gender'],
                            'sleeve_model' => $data['sleeve_model'],
                            'pocket_model' => $data['pocket_model'],
                            'button_model' => $data['button_model'],
                            'LD' => $data['LD'],
                            'PB' => $data['PB'],
                            'PL' => $data['PL'],
                            'LB' => $data['LB'],
                            'LP' => $data['LP'],
                            'LPh' => $data['LPh'],
                        ]);
                        $record->update(['size_and_request_details' => $details]);
                    }),

                DeleteAction::make(),
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
                        $this->updateOrderSubtotal();
                        Notification::make()->success()->title('Harga berhasil diperbarui!')->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('bulk_edit_bahan')
                    ->label('Ubah Bahan')
                    ->icon('heroicon-o-swatch')
                    ->form(fn() => [
                        Select::make('bahan_id')
                            ->label('Bahan Baru')
                            ->options(Material::where('shop_id', Filament::getTenant()?->id)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['bahan_id' => $data['bahan_id']]);
                        Notification::make()->success()->title('Bahan berhasil diperbarui!')->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('bulk_edit_category')
                    ->label('Ubah Kategori')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Select::make('production_category')
                            ->label('Kategori Baru')
                            ->options([
                                'produksi' => 'Produksi',
                                'non_produksi' => 'Non-Produksi',
                                'jasa' => 'Jasa',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['production_category' => $data['production_category']]);
                        Notification::make()->success()->title('Kategori berhasil diperbarui!')->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                DeleteBulkAction::make()
                    ->after(function () {
                        $this->updateOrderSubtotal();
                    }),
            ])

            ->defaultSort('id', 'asc')
            ->striped()
            ->paginated(false);
    }

    /**
     * Recalculate order subtotal from all items
     */
    protected function updateOrderSubtotal(): void
    {
        $order = $this->getOwnerRecord();
        $subtotal = $order->orderItems()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));
        $order->update(['subtotal' => (int) $subtotal]);
    }
}
