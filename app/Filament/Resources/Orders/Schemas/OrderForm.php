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
                Grid::make(3)
                    ->schema([
                        Select::make('bulk_category')
                            ->label('Kategori Default')
                            ->options([
                                'produksi' => 'Konveksi',
                                'jasa' => 'Jasa',
                                'non_produksi' => 'Baju Jadi',
                            ])
                            ->default('produksi')
                            ->required(),
                        Select::make('bulk_material')
                            ->label('Material Default')
                            ->options(OrderResource::getBahanOptions())
                            ->allowHtml()
                            ->searchable(),
                        Select::make('bulk_product')
                            ->label('Baju Jadi (Jika Baju Jadi)')
                            ->options(OrderResource::getSupplierProductOptions())
                            ->allowHtml()
                            ->searchable(),
                    ]),

                // Atribut Default untuk Konveksi
                Grid::make(4)
                    ->schema([
                        Select::make('bulk_gender')
                            ->label('Jenis Kelamin')
                            ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                            ->default('L'),
                        Select::make('bulk_sleeve')
                            ->label('Model Lengan')
                            ->options(['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4'])
                            ->default('pendek'),
                        Select::make('bulk_pocket')
                            ->label('Model Saku')
                            ->options(['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Saku Tempel', 'bobok' => 'Saku Bobok', 'double' => 'Double Saku'])
                            ->default('tanpa_saku'),
                        Select::make('bulk_buttons')
                            ->label('Model Kancing')
                            ->options(['biasa' => 'Biasa', 'tertutup' => 'Snap/Tertutup'])
                            ->default('biasa'),
                    ])
                    ->visible(fn(Get $get) => $get('bulk_category') === 'produksi'),
                
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
                    ])
                    ->visible(fn(Get $get) => $get('bulk_category') === 'produksi'),
                
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
                                ->label('ukurbadan')
                                ->numeric()
                                ->placeholder('0')
                                ->hint('Slot kosong')
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
                    ->label(false)
                    ->content(new HtmlString('
                        <div class="hidden lg:grid grid-cols-12 gap-4 px-4 py-2 bg-gray-100/80 border-b border-gray-200 font-bold text-[10px] uppercase tracking-wider text-gray-500 rounded-t-xl sticky top-0 z-10">
                            <div class="col-span-3">Item / Pemesan</div>
                            <div class="col-span-2">Ukuran & Kategori</div>
                            <div class="col-span-3">Spek & Bahan</div>
                            <div class="col-span-2 text-right">Harga Satuan</div>
                            <div class="col-span-1 text-center">Qty</div>
                            <div class="col-span-1 text-right">Subtotal</div>
                        </div>
                    ')),

                Repeater::make('orderItems')
                    ->relationship('orderItems')
                    ->label(false)
                    ->schema([
                        // Baris Utama (Table Row)
                        Grid::make(12)
                            ->schema([
                                // 1. Nama Item (3)
                                TextInput::make('product_name')
                                    ->label('Nama/Item')
                                    ->hiddenLabel()
                                    ->placeholder('Nama Pemesan...')
                                    ->required()
                                    ->columnSpan(3),
                                
                                // 2. Sizing & Category (2)
                                Group::make([
                                    Select::make('size')
                                        ->label('Size')
                                        ->hiddenLabel()
                                        ->options(OrderResource::getStoreSizeOptions())
                                        ->placeholder('Size')
                                        ->searchable()
                                        ->columnSpan(1),
                                    Select::make('production_category')
                                        ->label('Kategori')
                                        ->hiddenLabel()
                                        ->options([
                                            'produksi' => 'Konveksi',
                                            'custom' => 'Custom',
                                            'non_produksi' => 'Baju Jadi',
                                            'jasa' => 'Jasa',
                                        ])
                                        ->placeholder('Kat')
                                        ->required()
                                        ->columnSpan(1),
                                ])->columns(2)->columnSpan(2),

                                // 3. Bahan & Spec Summary (3)
                                Group::make([
                                    Select::make('bahan_baju')
                                        ->label('Bahan')
                                        ->hiddenLabel()
                                        ->options(OrderResource::getBahanOptions())
                                        ->placeholder('Pilih Bahan...')
                                        ->allowHtml()
                                        ->columnSpan(2),
                                    Section::make('Spec')
                                        ->label(false)
                                        ->compact()
                                        ->collapsible()
                                        ->collapsed()
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    Select::make('gender')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->default('L'),
                                                    Select::make('sleeve_model')->options(['pendek' => 'Pendek', 'panjang' => 'Panjang', '3/4' => '3/4']),
                                                    Select::make('pocket_model')->options(['tanpa_saku' => 'Tanpa Saku', 'tempel' => 'Tempel', 'bobok' => 'Bobok']),
                                                    Select::make('button_model')->options(['biasa' => 'Biasa', 'tertutup' => 'Snap']),
                                                ])
                                        ])
                                        ->columnSpan(1),
                                ])->columns(3)->columnSpan(3),

                                // 4. Harga (2)
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => OrderResource::updateSubtotal($set, $get))
                                    ->extraInputAttributes(['class' => 'text-right font-medium'])
                                    ->columnSpan(2),

                                // 5. Qty (1)
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => OrderResource::updateSubtotal($set, $get))
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->columnSpan(1),

                                // 6. Total (1)
                                Placeholder::make('item_subtotal')
                                    ->label('Total')
                                    ->hiddenLabel()
                                    ->content(fn(Get $get) => new HtmlString('<div class="text-right font-bold text-primary-600">Rp ' . number_format((int)$get('price') * (int)$get('quantity'), 0, ',', '.') . '</div>'))
                                    ->columnSpan(1),
                            ]),


                                    Section::make('Ukuran Custom (Body Measurement)')
                                        ->collapsible()
                                        ->collapsed()
                                        ->compact()
                                        ->schema([
                                            Grid::make(3)
                                                ->schema([
                                                    TextInput::make('LD')->label('LD (Lebar Dada)')->numeric()->suffix('cm'),
                                                    TextInput::make('PB')->label('PB (Panjang Baju)')->numeric()->suffix('cm'),
                                                    TextInput::make('PL')->label('PL (Panjang Lengan)')->numeric()->suffix('cm'),
                                                    TextInput::make('LB')->label('LB (Lebar Bahu)')->numeric()->suffix('cm'),
                                                    TextInput::make('LP')->label('LP (Lingkar Perut)')->numeric()->suffix('cm'),
                                                    TextInput::make('LPh')->label('LPh (Lingkar Paha)')->numeric()->suffix('cm'),
                                                ]),
                                        ])->columnSpan(1),
                                ])
                            ->extraAttributes(['class' => 'mt-2 p-2 bg-gray-50/30 rounded-lg'])
                    ->itemLabel(fn(array $state): ?string => ($state['product_name'] ?? 'Item Baru') . ' - ' . ($state['size'] ?? 'No Size'))
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0) // Biar user generate aja
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
            ->label(false)
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
