<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Material;
use App\Models\Product;
use App\Models\StoreSize;
use App\Models\AddonOption;
use App\Models\PrintType;
use App\Models\Customer;
use App\Models\CustomerMeasurement;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

class OrderForm
{
    /**
     * Get the Bulk Generator section (S, M, L, etc.)
     */
    public static function getBulkGenerator(): Section
    {
        return Section::make('Bulk Entry (Penambahan Massal)')
            ->description('Gunakan bagian ini untuk memasukkan banyak item sekaligus ke dalam tabel di bawah.')
            ->icon('heroicon-o-plus-circle')
            ->collapsed() // Default tertutup biar gak menuhin layar kalau gak butuh
            ->compact()
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('bulk_category')
                            ->label('Kategori Default')
                            ->options([
                                'produksi' => 'Konveksi',
                                'jasa' => 'Jasa',
                                'non_produksi' => 'Baju Jadi',
                            ])
                            ->default('produksi')
                            ->live(),
                        Select::make('bulk_material')
                            ->label('Material Default')
                            ->options(OrderResource::getBahanOptions())
                            ->visible(fn(Get $get) => $get('bulk_category') === 'produksi')
                            ->allowHtml()
                            ->searchable(),
                        Select::make('bulk_product')
                            ->label('Baju Jadi (Pilih Produk)')
                            ->options(OrderResource::getSupplierProductOptions())
                            ->visible(fn(Get $get) => $get('bulk_category') === 'non_produksi')
                            ->allowHtml()
                            ->searchable(),
                    ]),

                // Atribut Default untuk Konveksi
                Grid::make(4)
                    ->schema([
                        Select::make('bulk_gender')
                            ->label('Jenis Kelamin')
                            ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                            ->placeholder('Opsi...'),
                        Select::make('bulk_sleeve')
                            ->label('Model Lengan')
                            ->options(['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4'])
                            ->placeholder('Opsi...'),
                        Select::make('bulk_pocket')
                            ->label('Model Saku')
                            ->options(['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Saku Tempel', 'bobok' => 'Saku Bobok', 'double' => 'Double Saku'])
                            ->placeholder('Opsi...'),
                        Select::make('bulk_buttons')
                            ->label('Model Kancing')
                            ->options(['biasa' => 'Biasa', 'tertutup' => 'Snap/Tertutup'])
                            ->placeholder('Opsi...'),
                    ]),

                Grid::make(2)
                    ->schema([
                        Toggle::make('bulk_is_tunic')
                            ->label('Pakai Tunik?')
                            ->live(),
                        TextInput::make('bulk_tunic_fee')
                            ->label('Biaya Tambahan Tunik')
                            ->numeric()
                            ->prefix('Rp')
                            ->visible(fn(Get $get) => (bool) $get('bulk_is_tunic')),
                    ]),

                Group::make([
                    Placeholder::make('size_counts_label')
                        ->content(new HtmlString('<span class="text-xs font-bold uppercase tracking-wider text-gray-500">Jumlah per Ukuran (Pcs)</span>')),
                    Grid::make(8) // S, M, L, XL, XXL, XXXL, XXXL, XXXL (or custom slots)
                        ->schema(function () {
                            $sizes = OrderResource::getStoreSizeOptions();
                            $inputs = [];
                            foreach ($sizes as $size) {
                                $inputs[] = TextInput::make("qty_{$size}")
                                    ->label($size)
                                    ->numeric()
                                    ->placeholder('0')
                                    ->extraInputAttributes(['class' => 'text-center']);
                            }
                            // Tambah slot custom (tanpa nama ukuran fix)
                            $inputs[] = TextInput::make('qty_custom_slots')
                                ->label('UkurBadan')
                                ->numeric()
                                ->placeholder('0')
                                ->extraInputAttributes(['class' => 'text-center']);

                            return $inputs;
                        }),
                ])->extraAttributes(['class' => 'p-4 border rounded-xl bg-gray-50/50']),

                Action::make('generate_items')
                    ->label('Generate & Masukkan ke Tabel')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->action(function (Set $set, Get $get) {
                        $items = $get('orderItems') ?? [];
                        $category = $get('bulk_category');
                        $material = $get('bulk_material');
                        $product = $get('bulk_product');

                        // Add extra attributes to the data
                        $gender = $get('bulk_gender');
                        $sleeve = $get('bulk_sleeve');
                        $pocket = $get('bulk_pocket');
                        $buttons = $get('bulk_buttons');
                        $isTunic = (bool) $get('bulk_is_tunic');
                        $tunicFee = (int) ($get('bulk_tunic_fee') ?? 0);

                        // Generate dari ukuran fix
                        $sizes = \App\Filament\Resources\Orders\OrderResource::getStoreSizeOptions();
                        foreach ($sizes as $size) {
                            $qtyVal = (int) ($get("qty_{$size}") ?? 0);
                            for ($i = 0; $i < $qtyVal; $i++) {
                                $items[] = [
                                    'id' => (string) Str::uuid(),
                                    'production_category' => $category,
                                    'product_name' => '',
                                    'bahan_baju' => $material,
                                    'supplier_product' => $product,
                                    'size' => $size,
                                    'price' => 0,
                                    'quantity' => 1,
                                    'size_type' => 'standard',
                                    'gender' => $gender,
                                    'sleeve_model' => $sleeve,
                                    'pocket_model' => $pocket,
                                    'button_model' => $buttons,
                                    'is_tunic' => $isTunic,
                                    'tunic_fee' => $tunicFee,
                                ];
                            }
                            $set("qty_{$size}", null);
                        }

                        // Generate dari ukurbadan (custom slots)
                        $customQty = (int) ($get('qty_custom_slots') ?? 0);
                        for ($i = 0; $i < $customQty; $i++) {
                            $items[] = [
                                'id' => (string) Str::uuid(),
                                'production_category' => 'custom', // Auto-set to custom for measurements
                                'product_name' => '',
                                'bahan_baju' => $material,
                                'supplier_product' => $product,
                                'size' => 'Custom',
                                'price' => 0,
                                'quantity' => 1,
                                'size_type' => 'custom',
                                'gender' => $gender,
                                'sleeve_model' => $sleeve,
                                'pocket_model' => $pocket,
                                'button_model' => $buttons,
                                'is_tunic' => $isTunic,
                                'tunic_fee' => $tunicFee,
                            ];
                        }
                        $set('qty_custom_slots', null);

                        $set('orderItems', $items);
                        OrderResource::updateSubtotal($set, $get);
                    })
                    ->extraAttributes(['class' => 'w-full shadow-sm']),
            ]);
    }

    /**
     * Get the Item Pool (Main Repeater)
     */
    public static function getItemPool(): Section
    {
        return Section::make('Daftar Item Pesanan (Pool)')
            ->description('Kustomisasi setiap item yang telah digenerate di sini.')
            ->headerActions([
                Action::make('bulk_price')
                    ->label('Ubah Harga Semua')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->form([
                        TextInput::make('new_price')
                            ->label('Harga Baru')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (Set $set, Get $get, array $data) {
                        $items = $get('orderItems') ?? [];
                        foreach ($items as &$item) {
                            $item['price'] = $data['new_price'];
                        }
                        $set('orderItems', $items);
                        \Filament\Notifications\Notification::make()->title('Harga semua item berhasil diupdate')->success()->send();
                    }),
                Action::make('bulk_material')
                    ->label('Ubah Bahan Semua')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->form([
                        Select::make('new_material')
                            ->label('Bahan/Produk Baru')
                            ->options(\App\Models\Material::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Set $set, Get $get, array $data) {
                        $items = $get('orderItems') ?? [];
                        foreach ($items as &$item) {
                            $item['bahan_baju'] = $data['new_material'];
                        }
                        $set('orderItems', $items);
                        \Filament\Notifications\Notification::make()->title('Bahan semua item berhasil diupdate')->success()->send();
                    }),
                Action::make('clear_pool')
                    ->label('Kosongkan Tabel')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(fn(Set $set, Get $get) => $set('orderItems', [])),
            ])
            ->schema([
                static::getPersistenceScript(), // State recovery script
                Placeholder::make('table_header')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <style>
                            .order-items-pool .fi-fo-repeater-item-header { display: none !important; }
                            .order-items-pool .fi-fo-repeater-item {
                                background: white !important;
                                border: none !important;
                                border-bottom: 1px solid #e5e7eb !important;
                                box-shadow: none !important;
                                padding: 0 !important;
                                margin: 0 !important;
                                border-radius: 0 !important;
                            }
                            .order-items-pool .fi-fo-repeater-item:hover { background: #f9fafb !important; }
                            .order-items-pool .fi-fo-repeater-item > div { padding: 4px !important; }
                            /* Hide validation asterisks in table */
                            .order-items-pool .fi-fo-field-wrp-label sup { display: none !important; }
                            .order-items-pool input, .order-items-pool select {
                                border: 1px solid transparent !important;
                                box-shadow: none !important;
                                font-size: 13px !important;
                                padding: 2px 4px !important;
                            }
                            .order-items-pool input:focus, .order-items-pool select:focus {
                                border-color: #7c3aed !important;
                                background: white !important;
                            }
                        </style>
                        <div class="hidden lg:grid grid-cols-12 gap-2 px-4 py-3 bg-gray-50 border-b border-gray-200 font-bold text-[10px] uppercase tracking-[0.05em] text-gray-500 rounded-t-xl sticky top-0 z-10">
                            <div class="col-span-3">Item / Pemesan</div>
                            <div class="col-span-1">Size</div>
                            <div class="col-span-1 text-center">Kat</div>
                            <div class="col-span-3">Bahan / Produk</div>
                            <div class="col-span-2 text-right">Harga</div>
                            <div class="col-span-1 text-center">Qty</div>
                            <div class="col-span-1 text-right pr-8">Aksi</div>
                        </div>
                    ')),

                Repeater::make('orderItems')
                    ->relationship('orderItems')
                    ->hiddenLabel()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('product_name')
                                    ->hiddenLabel()
                                    ->placeholder('Nama/Item...')
                                    ->required()
                                    ->columnSpan(3),
                                Select::make('size')
                                    ->hiddenLabel()
                                    ->options(OrderResource::getStoreSizeOptions())
                                    ->placeholder('Size')
                                    ->searchable()
                                    ->columnSpan(1),
                                Select::make('production_category')
                                    ->hiddenLabel()
                                    ->options([
                                        'produksi' => 'Konv.',
                                        'custom' => 'Cust.',
                                        'non_produksi' => 'Jadi',
                                        'jasa' => 'Jasa',
                                    ])
                                    ->required()
                                    ->columnSpan(1),
                                Select::make('bahan_baju')
                                    ->hiddenLabel()
                                    ->options(OrderResource::getBahanOptions())
                                    ->placeholder('Bahan...')
                                    ->allowHtml()
                                    ->columnSpan(3),
                                TextInput::make('price')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => OrderResource::updateSubtotal($set, $get))
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => OrderResource::updateSubtotal($set, $get))
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->columnSpan(1),
                                Placeholder::make('item_subtotal')
                                    ->hiddenLabel()
                                    ->content(fn(Get $get) => new HtmlString('<div class="text-right font-bold text-primary-600 pr-2">Rp ' . number_format((int) $get('price') * (int) $get('quantity'), 0, ',', '.') . '</div>'))
                                    ->columnSpan(1),
                            ])->gap(1),
                    ])
                    ->extraAttributes(['class' => 'order-items-pool'])
                    ->extraItemActions([
                        Action::make('edit_details')
                            ->label('Detail')
                            ->icon('heroicon-m-adjustments-vertical')
                            ->modalSubmitActionLabel('Simpan')
                            ->form([
                                Section::make('Spesifikasi & Ukuran Badan')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('gender')->label('Gender')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                                        Select::make('sleeve_model')->label('Lengan')->options(['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4']),
                                        Select::make('pocket_model')->label('Saku')->options(['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok']),
                                        Select::make('button_model')->label('Kancing')->options(['biasa' => 'Biasa', 'snap/snap' => 'Snap/Tertutup']),
                                        TextInput::make('LD')->label('LD (L. Dada)')->numeric()->suffix('cm'),
                                        TextInput::make('PB')->label('PB (P. Baju)')->numeric()->suffix('cm'),
                                        TextInput::make('PL')->label('PL (P. Lengan)')->numeric()->suffix('cm'),
                                        TextInput::make('LB')->label('LB (L. Bahu)')->numeric()->suffix('cm'),
                                        TextInput::make('LP')->label('LP (L. Perut)')->numeric()->suffix('cm'),
                                        TextInput::make('LPh')->label('LPh (L. Paha)')->numeric()->suffix('cm'),
                                    ]),
                            ])
                            ->action(function () { }),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Tambah Item Manual')
                    ->live()
                    ->afterStateUpdated(fn(Set $set, Get $get) => OrderResource::updateSubtotal($set, $get))
                    ->reorderable(true)
                    ->columnSpanFull(),
            ]);
    }

    protected static function getPersistenceScript(): Placeholder
    {
        return Placeholder::make('persistence_script')
            ->hiddenLabel()
            ->content(new HtmlString('
                <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const formKey = "order_form_draft_" + ' . Filament::getTenant()?->id . ';
                    
                    document.addEventListener("livewire:initialized", () => {
                        const component = Livewire.find(document.querySelector("[wire\\:id]").getAttribute("wire:id"));
                        
                        // Load draft ONLY on create page
                        if (window.location.pathname.endsWith("/create")) {
                            const draft = localStorage.getItem(formKey);
                            if (draft) {
                                try {
                                    const data = JSON.parse(draft);
                                    component.set("data", data);
                                    console.log("Order Draft Loaded");
                                } catch (e) { console.error("Draft load error", e); }
                            }
                        }

                        // Save draft on ANY livewire update
                        Livewire.hook("commit", ({ component: cmp, succeed }) => {
                            if (cmp.id === component.id) {
                                succeed(() => {
                                    const data = component.get("data");
                                    // Don\'t save if data is empty or just default
                                    if (data && data.orderItems && data.orderItems.length > 0) {
                                        localStorage.setItem(formKey, JSON.stringify(data));
                                    }
                                });
                            }
                        });
                        
                        // Clear draft when "Order Created" (user should dispatch this from Page class if possible)
                        // Or we can just check if we are on a different page now
                    });
                });
                </script>
            '));
    }
}
