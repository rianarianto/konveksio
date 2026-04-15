<?php

namespace App\Livewire;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Material;
use App\Models\StoreSize;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class IntegratedOrderItemsTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

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
            'custom' => 'Custom',
            'non_produksi' => 'Baju Jadi',
            'jasa' => 'Jasa',
        ];

        return $table
            ->query(OrderItem::query()->where('order_id', $this->order->id))
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
                        $this->refreshOrderData();
                    }),

                TextInputColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->rules(['required', 'numeric', 'min:1'])
                    ->afterStateUpdated(function () {
                        $this->refreshOrderData();
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
                // Fix: Namespace issue. In some versions it might be Filament\Tables\Actions\CreateAction
                // If not found, we use a custom Action to simulate creation
                Action::make('create_item')
                    ->label('Tambah Item')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Tambah Item Baru')
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
                    ->action(function (array $data) {
                        $this->order->orderItems()->create($data);
                        $this->refreshOrderData();
                        Notification::make()->success()->title('Item berhasil ditambahkan')->send();
                    }),

                Action::make('bulk_generate')
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
                                'production_category' => 'custom',
                                'bahan_id' => $bahanId,
                                'price' => $price,
                                'quantity' => 1,
                            ];
                        }

                        if (empty($items)) return;

                        foreach ($items as $item) {
                            $this->order->orderItems()->create($item);
                        }

                        $this->refreshOrderData();
                        Notification::make()->success()->title(count($items) . ' item berhasil ditambahkan!')->send();
                    }),
            ])
            ->actions([
                Action::make('edit_specs')
                    ->label('Detail')
                    ->icon('heroicon-m-adjustments-vertical')
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
                    ->fillForm(fn(OrderItem $record) => $record->size_and_request_details ?? [])
                    ->action(function (OrderItem $record, array $data): void {
                        $record->update(['size_and_request_details' => $data]);
                    }),
                DeleteAction::make()
                    ->after(fn () => $this->refreshOrderData()),
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
                DeleteBulkAction::make()
                    ->after(fn () => $this->refreshOrderData()),
            ])
            ->paginated(false);
    }

    protected function refreshOrderData(): void
    {
        // 1. Recalculate subtotal in DB
        $subtotal = $this->order->orderItems()->sum(DB::raw('price * quantity'));
        $this->order->update(['subtotal' => (int) $subtotal]);

        // 2. Dispatch event to parent to refresh sidebar summary
        // Note: In Filament v3/v4, you can use 'refreshOrderResource' or similar
        $this->dispatch('refreshOrderSummary');
    }

    public function render()
    {
        return view('livewire.integrated-order-items-table');
    }
}
