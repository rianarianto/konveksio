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
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
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
            'produksi' => 'Konveksi',
            'non_produksi' => 'Baju Jadi',
            'jasa' => 'Jasa',
        ];

        return $table
            ->columns([
                TextInputColumn::make('product_name')
                    ->label('Nama / Item')
                    ->sortable()
                    ->searchable()
                    ->rules(['required', 'max:255']),

                SelectColumn::make('size')
                    ->label('Size')
                    ->options($sizeOptions)
                    ->sortable(),

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
                        $this->updateOrderSubtotal();
                    }),

                TextInputColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->rules(['required', 'numeric', 'min:1'])
                    ->afterStateUpdated(function () {
                        $this->updateOrderSubtotal();
                    }),

                TextColumn::make('subtotal')
                    ->label('Total')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw('price * quantity ' . $direction);
                    })
                    ->state(fn(OrderItem $record) => $record->price * $record->quantity)
                    ->money('IDR')
                    ->color('primary')
                    ->weight('bold')
                    ->alignEnd(),
            ])

            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->label('Tambah Item')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('product_name')
                            ->label('Nama Item')
                            ->required(),
                        Select::make('size')
                            ->label('Ukuran')
                            ->options($sizeOptions)
                            ->searchable(),
                        Select::make('production_category')
                            ->label('Kategori')
                            ->options($categoryOptions)
                            ->required()
                            ->default('produksi'),
                        Select::make('bahan_id')
                            ->label('Bahan')
                            ->options($bahanOptions)
                            ->searchable(),
                        TextInput::make('price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(0),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->default(1),
                    ])
                    ->after(function () {
                        $this->updateOrderSubtotal();
                    }),

                // ─── BULK GENERATOR ────────────────────────────────────────
                \Filament\Tables\Actions\Action::make('bulk_generate')
                    ->label('Bulk Generate')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->form(function () use ($sizeOptions, $bahanOptions, $categoryOptions) {
                        $fields = [
                            Select::make('bulk_category')
                                ->label('Kategori')
                                ->options($categoryOptions)
                                ->default('produksi')
                                ->required()
                                ->columnSpan(1),
                            Select::make('bulk_bahan')
                                ->label('Bahan')
                                ->options($bahanOptions)
                                ->searchable()
                                ->columnSpan(1),
                            TextInput::make('bulk_price')
                                ->label('Harga Default')
                                ->numeric()
                                ->prefix('Rp')
                                ->default(0)
                                ->columnSpan(1),
                        ];

                        // Size-based qty fields
                        foreach ($sizeOptions as $key => $label) {
                            $fields[] = TextInput::make("qty_{$key}")
                                ->label("Qty {$label}")
                                ->numeric()
                                ->default(0)
                                ->columnSpan(1);
                        }

                        $fields[] = TextInput::make('qty_custom')
                            ->label('Qty Custom (Ukur Badan)')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1);

                        return $fields;
                    })
                    ->modalWidth('lg')
                    ->modalHeading('Bulk Generate Items')
                    ->modalDescription('Masukkan jumlah per ukuran. Item akan langsung ditambahkan ke tabel.')
                    ->action(function (array $data) use ($sizeOptions) {
                        $category = $data['bulk_category'] ?? 'produksi';
                        $bahanId = $data['bulk_bahan'] ?? null;
                        $price = (int) ($data['bulk_price'] ?? 0);
                        $items = [];

                        foreach ($sizeOptions as $key => $label) {
                            $qty = (int) ($data["qty_{$key}"] ?? 0);
                            for ($i = 0; $i < $qty; $i++) {
                                $items[] = [
                                    'product_name' => '',
                                    'size' => $key,
                                    'production_category' => $category,
                                    'bahan_id' => $bahanId,
                                    'price' => $price,
                                    'quantity' => 1,
                                ];
                            }
                        }

                        $customQty = (int) ($data['qty_custom'] ?? 0);
                        for ($i = 0; $i < $customQty; $i++) {
                            $items[] = [
                                'product_name' => '',
                                'size' => 'Custom',
                                'production_category' => $category,
                                'bahan_id' => $bahanId,
                                'price' => $price,
                                'quantity' => 1,
                            ];
                        }

                        if (empty($items)) {
                            Notification::make()
                                ->warning()
                                ->title('Tidak ada item yang digenerate')
                                ->body('Masukkan minimal 1 qty pada salah satu ukuran.')
                                ->send();
                            return;
                        }

                        $order = $this->getOwnerRecord();
                        foreach ($items as $item) {
                            $order->orderItems()->create($item);
                        }

                        $this->updateOrderSubtotal();

                        Notification::make()
                            ->success()
                            ->title(count($items) . ' item berhasil ditambahkan!')
                            ->send();
                    }),
            ])

            ->actions([
                \Filament\Tables\Actions\Action::make('edit_specs')
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
                                'produksi' => 'Konveksi',
                                'non_produksi' => 'Baju Jadi',
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
