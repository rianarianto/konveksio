<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Models\Customer;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'Pesanan';

    public static function canAccess(): bool
    {
        return auth()->user()->role !== 'designer';
    }

    protected static bool $isScopedToTenant = true;

    // ─── Bahan baju dengan warna swatch (sementara hardcode, nanti dari data master) ───
    protected static function getBahanOptions(): array
    {
        $bahanList = [
            'JN | Parasut | Hitam' => ['hex' => '#1a1a1a', 'text' => '#ffffff'],
            'JN | Parasut | Putih' => ['hex' => '#f0f0f0', 'text' => '#333333'],
            'JN | Parasut | Hijau' => ['hex' => '#166534', 'text' => '#ffffff'],
            'JN | Parasut | Navy' => ['hex' => '#1e3a5f', 'text' => '#ffffff'],
            'JN | Parasut | Merah' => ['hex' => '#991b1b', 'text' => '#ffffff'],
            'JN | Parasut | Abu' => ['hex' => '#6b7280', 'text' => '#ffffff'],
            'JN | Drill | Hitam' => ['hex' => '#2d2d2d', 'text' => '#ffffff'],
            'JN | Drill | Coklat' => ['hex' => '#8b5e3c', 'text' => '#ffffff'],
            'JN | Drill | Cream' => ['hex' => '#f5e6c8', 'text' => '#333333'],
            'JN | Polo | Putih' => ['hex' => '#ffffff', 'text' => '#333333'],
            'JN | Polo | Hitam' => ['hex' => '#111111', 'text' => '#ffffff'],
            'JN | Polo | Merah' => ['hex' => '#b91c1c', 'text' => '#ffffff'],
            'Lainnya' => ['hex' => '#e5e7eb', 'text' => '#374151'],
        ];

        $options = [];
        foreach ($bahanList as $label => $color) {
            $options[$label] = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                . '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' . $color['hex'] . ';border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
                . '<span>' . htmlspecialchars($label) . '</span>'
                . '</span>';
        }
        return $options;
    }

    // ─── Supplier produk dengan warna swatch ─────────────────────────────────
    protected static function getSupplierProductOptions(): array
    {
        $list = [
            'kaos_cotton'   => ['hex' => '#f59e0b', 'text' => '#ffffff', 'label' => 'Kaos Cotton'],
            'kaos_rib'      => ['hex' => '#d97706', 'text' => '#ffffff', 'label' => 'Kaos Rib / Waffle'],
            'polo_shirt'    => ['hex' => '#ea580c', 'text' => '#ffffff', 'label' => 'Polo Shirt'],
            'jaket_parasut' => ['hex' => '#2563eb', 'text' => '#ffffff', 'label' => 'Jaket Parasut'],
            'jaket_hoodie'  => ['hex' => '#1d4ed8', 'text' => '#ffffff', 'label' => 'Jaket Hoodie'],
            'jaket_bomber'  => ['hex' => '#1e40af', 'text' => '#ffffff', 'label' => 'Jaket Bomber'],
            'sweater'       => ['hex' => '#7c3aed', 'text' => '#ffffff', 'label' => 'Sweater'],
            'kemeja'        => ['hex' => '#16a34a', 'text' => '#ffffff', 'label' => 'Kemeja'],
            'rompi'         => ['hex' => '#15803d', 'text' => '#ffffff', 'label' => 'Rompi'],
            'celana_jogger' => ['hex' => '#374151', 'text' => '#ffffff', 'label' => 'Celana Jogger'],
            'topi'          => ['hex' => '#92400e', 'text' => '#ffffff', 'label' => 'Topi / Cap'],
            'totebag'       => ['hex' => '#a16207', 'text' => '#ffffff', 'label' => 'Totebag'],
            'lainnya'       => ['hex' => '#e5e7eb', 'text' => '#374151', 'label' => 'Lainnya'],
        ];

        $options = [];
        foreach ($list as $key => $item) {
            $options[$key] = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                . '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' . $item['hex'] . ';border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
                . '<span>' . htmlspecialchars($item['label']) . '</span>'
                . '</span>';
        }
        return $options;
    }

    // ─── Request tambahan ────────────────────────────────────────────────────
    protected static array $requestTambahanOptions = [
        'Saku Semi Klewang' => 'Saku Semi Klewang',
        'Saku Biasa' => 'Saku Biasa',
        'Kancing Ekstra' => 'Kancing Ekstra',
        'Bordir Nama' => 'Bordir Nama',
        'Label Jahit' => 'Label Jahit',
        'Resleting' => 'Resleting',
        'Lainnya' => 'Lainnya',
    ];

    // ─── Lokasi sablon/bordir (kombinasi titik, nanti dari data master) ──────
    protected static array $lokasiSablonOptions = [
        'Dada Kiri' => 'Dada Kiri',
        'Dada Kanan' => 'Dada Kanan',
        'Dada Kiri + Dada Kanan' => 'Dada Kiri + Dada Kanan',
        'Punggung' => 'Punggung',
        'Dada Kiri + Punggung' => 'Dada Kiri + Punggung',
        'Dada Kanan + Punggung' => 'Dada Kanan + Punggung',
        'Dada Kiri + Dada Kanan + Punggung' => 'Dada Kiri + Dada Kanan + Punggung',
        'Lengan Kiri' => 'Lengan Kiri',
        'Lengan Kanan' => 'Lengan Kanan',
        'Lengan Kiri + Lengan Kanan' => 'Lengan Kiri + Lengan Kanan',
        'Lengan Kiri + Lengan Kanan + Punggung' => 'Lengan Kiri + Lengan Kanan + Punggung',
        'Dada Kiri + Lengan Kanan + Punggung' => 'Dada Kiri + Lengan Kanan + Punggung',
        'Dada Kanan + Lengan Kiri + Punggung' => 'Dada Kanan + Lengan Kiri + Punggung',
        'Full (Dada Ka/Ki + Lengan Ka/Ki + Punggung)' => 'Full (Dada Ka/Ki + Lengan Ka/Ki + Punggung)',
        'Lainnya' => 'Lainnya',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // LEFT SIDE (Main Content) - 2 columns
                Group::make([
                    // Section 1: Data Pesanan
                    Section::make('Data Pesanan')
                        ->schema([
                            TextInput::make('order_number')
                                ->label('No. Pesanan')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Auto-generated'),

                            DatePicker::make('order_date')
                                ->label('Tanggal Pesanan')
                                ->required()
                                ->default(now())
                                ->native(false),

                            DatePicker::make('deadline')
                                ->label('Deadline')
                                ->required()
                                ->native(false)
                                ->minDate(now()),
                        ])
                        ->columns(3),

                    // Section 2: Data Pelanggan
                    Section::make('Data Pelanggan')
                        ->schema([
                            Select::make('customer_id')
                                ->label('Nama')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nama Pelanggan')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('phone')
                                        ->label('Kontak')
                                        ->required()
                                        ->tel()
                                        ->maxLength(255),
                                    Textarea::make('address')
                                        ->label('Alamat')
                                        ->required()
                                        ->rows(3),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $customer = Customer::create([
                                        'shop_id' => \Filament\Facades\Filament::getTenant()->id,
                                        'name' => $data['name'],
                                        'phone' => $data['phone'],
                                        'address' => $data['address'],
                                    ]);
                                    return $customer->id;
                                })
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        $customer = Customer::find($state);
                                        if ($customer) {
                                            $set('customer_phone', $customer->phone);
                                            $set('customer_address', $customer->address);
                                        }
                                    } else {
                                        $set('customer_phone', null);
                                        $set('customer_address', null);
                                    }
                                }),

                            TextInput::make('customer_phone')
                                ->label('Kontak')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(1),

                            Textarea::make('customer_address')
                                ->label('Alamat')
                                ->disabled()
                                ->dehydrated(false)
                                ->rows(3)
                                ->columnSpan('full'),

                            Placeholder::make('maps_link')
                                ->label(false)
                                ->content(function (Get $get): HtmlString|string {
                                    $address = $get('customer_address');
                                    if (empty($address))
                                        return '';
                                    $url = 'https://www.google.com/maps/search/' . urlencode($address);
                                    return new HtmlString(
                                        '<a href="' . $url . '" target="_blank" rel="noopener" '
                                        . 'style="display:inline-flex;align-items:center;gap:6px;color:#7c3aed;font-size:13px;font-weight:600;text-decoration:none;">'
                                        . '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/></svg>'
                                        . 'Buka di Google Maps</a>'
                                    );
                                })
                                ->columnSpan('full'),
                        ])
                        ->columns(2),

                    // Section 3: Data Produk Pesanan — Repeater dengan card view
                    Section::make('Data Produk Pesanan')
                        ->schema([
                            Repeater::make('orderItems')
                                ->relationship()
                                ->schema([
                                    // ── LAYER 1: Nama Produk + Kategori ───────────────
                                    Group::make([
                                        TextInput::make('product_name')
                                            ->label('Nama Produk Pesanan')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('production_category')
                                            ->label('Kategori Pesanan')
                                            ->options([
                                                'produksi'      => 'Produksi (Size Toko)',
                                                'custom'        => 'Produksi Custom (Ukur Badan)',
                                                'non_produksi'  => 'Non-Produksi (Barang Jadi Supplier)',
                                                'jasa'          => 'Jasa',
                                            ])
                                            ->default('produksi')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                            ->columnSpan(2),
                                    ])->columns(5),

                                    // ── LAYER 2: Pilih Bahan (hanya Produksi & Custom) ─────
                                    Select::make('bahan_baju')
                                        ->label('Bahan Baju')
                                        ->options(static::getBahanOptions())
                                        ->allowHtml()
                                        ->searchable()
                                        ->dehydrated(true)
                                        ->visible(fn(Get $get) => in_array($get('production_category'), ['produksi', 'custom']))
                                        ->columnSpanFull(),

                                    Select::make('supplier_product')
                                        ->label('Jenis Produk Supplier')
                                        ->options(static::getSupplierProductOptions())
                                        ->allowHtml()
                                        ->searchable()
                                        ->dehydrated(true)
                                        ->placeholder('Pilih jenis produk supplier...')
                                        ->visible(fn(Get $get) => $get('production_category') === 'non_produksi')
                                        ->columnSpanFull(),

                                    // ═══════════════════════════════════════════════════
                                    // ── ALUR A: PRODUKSI (SIZE TOKO) ──────────────────
                                    // ═══════════════════════════════════════════════════

                                    // A1: Sablon/Bordir — 2 field statis (berlaku untuk semua varian)
                                    Section::make('Sablon / Bordir')
                                        ->schema([
                                            Group::make([
                                                Select::make('sablon_jenis')
                                                    ->label('Teknik')
                                                    ->options([
                                                        'Sablon' => 'Sablon',
                                                        'Bordir' => 'Bordir',
                                                        'DTF' => 'DTF',
                                                        'Lainnya' => 'Lainnya',
                                                    ])
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih teknik...'),

                                                Select::make('sablon_lokasi')
                                                    ->label('Lokasi / Titik')
                                                    ->options(static::$lokasiSablonOptions)
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih kombinasi lokasi...'),
                                            ])->columns(2),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'produksi')
                                        ->compact(),

                                    // A2: Varian Ukuran
                                    Section::make('Varian Ukuran')
                                        ->schema([
                                            Repeater::make('varian_ukuran')
                                                ->label(false)
                                                ->schema([
                                                    Select::make('ukuran')
                                                        ->label('Ukuran')
                                                        ->options([
                                                            'XS' => 'XS',
                                                            'S' => 'S',
                                                            'M' => 'M',
                                                            'L' => 'L',
                                                            'XL' => 'XL',
                                                            'XXL' => 'XXL',
                                                            'XXXL' => 'XXXL',
                                                        ])
                                                        ->required()
                                                        ->columnSpan(2),

                                                    TextInput::make('harga_satuan')
                                                        ->label('Harga Satuan')
                                                        ->numeric()
                                                        ->prefix('Rp')
                                                        ->required()
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(2),

                                                    TextInput::make('qty')
                                                        ->label('Kuantitas')
                                                        ->numeric()
                                                        ->required()
                                                        ->default(1)
                                                        ->minValue(1)
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(2),

                                                    Placeholder::make('subtotal_varian')
                                                        ->label('Subtotal')
                                                        ->content(function (Get $get): string {
                                                            $h = (int) ($get('harga_satuan') ?? 0);
                                                            $q = (int) ($get('qty') ?? 0);
                                                            return 'Rp ' . number_format($h * $q, 0, ',', '.');
                                                        })
                                                        ->columnSpan(2),
                                                ])
                                                ->columns(8)
                                                ->defaultItems(0)
                                                ->addActionLabel('+ Tambah Varian')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true)
                                                ->live()
                                                ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                ->deleteAction(fn($action) => $action->after(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))),

                                            // Baris total varian
                                            Placeholder::make('total_varian_summary')
                                                ->label(false)
                                                ->content(function (Get $get): HtmlString {
                                                    $varian = $get('varian_ukuran') ?? [];
                                                    $totalQty = 0;
                                                    $totalHarga = 0;
                                                    foreach ($varian as $v) {
                                                        $q = (int) ($v['qty'] ?? 0);
                                                        $h = (int) ($v['harga_satuan'] ?? 0);
                                                        $totalQty += $q;
                                                        $totalHarga += $q * $h;
                                                    }
                                                    return new HtmlString(
                                                        '<div style="display:flex;justify-content:space-between;padding:8px 4px;border-top:1px solid #e9d5ff;margin-top:4px;">'
                                                        . '<span style="font-weight:700;color:#374151;">Total</span>'
                                                        . '<span style="color:#6b7280;">' . $totalQty . ' pcs</span>'
                                                        . '<span style="font-weight:700;color:#7c3aed;">Rp ' . number_format($totalHarga, 0, ',', '.') . '</span>'
                                                        . '</div>'
                                                    );
                                                }),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'produksi')
                                        ->compact(),

                                    // A3: Request Tambahan
                                    Section::make('Request Tambahan')
                                        ->description('Tambahan spesifik per ukuran (contoh: saku khusus ukuran S saja)')
                                        ->schema([
                                            Repeater::make('request_tambahan')
                                                ->label(false)
                                                ->schema([
                                                    // Baris 1: Jenis + Ukuran
                                                    Select::make('jenis')
                                                        ->label('Jenis Tambahan')
                                                        ->options(static::$requestTambahanOptions)
                                                        ->searchable()
                                                        ->required()
                                                        ->columnSpan(1),

                                                    Select::make('ukuran')
                                                        ->label('Untuk Ukuran')
                                                        ->options(function (Get $get): array {
                                                            $varian = $get('../../varian_ukuran') ?? [];
                                                            $opts = [];
                                                            $totalQty = 0;
                                                            foreach ($varian as $v) {
                                                                $uk = $v['ukuran'] ?? null;
                                                                $qty = (int) ($v['qty'] ?? 0);
                                                                $totalQty += $qty;
                                                                if ($uk) {
                                                                    $opts[$uk] = $uk . ' (' . $qty . ' pcs)';
                                                                }
                                                            }
                                                            // Opsi semua ukuran di paling atas
                                                            if ($totalQty > 0) {
                                                                $opts = ['__semua__' => '✦ Semua Ukuran (' . $totalQty . ' pcs total)'] + $opts;
                                                            }
                                                            return $opts;
                                                        })
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                            if ($state === '__semua__') {
                                                                // Auto-fill qty dengan total semua varian
                                                                $totalQty = 0;
                                                                foreach ($get('../../varian_ukuran') ?? [] as $v) {
                                                                    $totalQty += (int) ($v['qty'] ?? 0);
                                                                }
                                                                $set('qty_tambahan', $totalQty > 0 ? $totalQty : null);
                                                            } else {
                                                                $set('qty_tambahan', null);
                                                            }
                                                        })
                                                        ->columnSpan(1),

                                                    // Baris 2: Jumlah + Harga
                                                    TextInput::make('qty_tambahan')
                                                        ->label('Jumlah')
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->required()
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                            // Tetap recalc meskipun melebihi batas
                                                            // (validasi ditangani helperText + rules saat save)
                                                            static::recalcItemTotal($set, $get);
                                                        })
                                                        ->helperText(function (Get $get, $state): HtmlString|string {
                                                            if (!$state || !$get('ukuran')) return '';
                                                            $ukuran = $get('ukuran');
                                                            $varian = $get('../../varian_ukuran') ?? [];
                                                            // Hitung max sesuai pilihan ukuran
                                                            $max = 0;
                                                            if ($ukuran === '__semua__') {
                                                                foreach ($varian as $v) {
                                                                    $max += (int) ($v['qty'] ?? 0);
                                                                }
                                                            } else {
                                                                foreach ($varian as $v) {
                                                                    if (($v['ukuran'] ?? null) === $ukuran) {
                                                                        $max = (int) ($v['qty'] ?? 0);
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                            if ($max > 0 && (int) $state > $max) {
                                                                return new HtmlString(
                                                                    '<span style="color:#ef4444;font-size:12px;font-weight:500;">⚠ Melebihi batas! Maks. ' . $max . ' pcs untuk ukuran ' . ($ukuran === '__semua__' ? 'semua' : $ukuran) . '</span>'
                                                                );
                                                            }
                                                            return '';
                                                        })
                                                        ->rules([
                                                            fn(Get $get): \Closure => function ($attribute, $value, $fail) use ($get) {
                                                                $ukuran = $get('ukuran');
                                                                if (!$ukuran) return;
                                                                $varian = $get('../../varian_ukuran') ?? [];
                                                                $max = 0;
                                                                if ($ukuran === '__semua__') {
                                                                    foreach ($varian as $v) {
                                                                        $max += (int) ($v['qty'] ?? 0);
                                                                    }
                                                                } else {
                                                                    foreach ($varian as $v) {
                                                                        if (($v['ukuran'] ?? null) === $ukuran) {
                                                                            $max = (int) ($v['qty'] ?? 0);
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                if ($max > 0 && (int) $value > $max) {
                                                                    $fail('Jumlah melebihi stok ' . ($ukuran === '__semua__' ? 'semua ukuran' : 'ukuran ' . $ukuran) . ' (maks. ' . $max . ' pcs).');
                                                                }
                                                            },
                                                        ])
                                                        ->columnSpan(1),

                                                    TextInput::make('harga_extra_satuan')
                                                        ->label('Harga Tambahan/unit')
                                                        ->numeric()
                                                        ->prefix('Rp')
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(1),
                                                ])
                                                ->columns(2)
                                                ->defaultItems(0)
                                                ->addActionLabel('+ Tambah Request')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true)
                                                ->live()
                                                ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                ->deleteAction(fn($action) => $action->after(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'produksi')
                                        ->compact(),


                                    // ═══════════════════════════════════════════════════
                                    // ── ALUR B: PRODUKSI CUSTOM (UKUR BADAN) ──────────
                                    // ═══════════════════════════════════════════════════

                                    // B0: Load dari ukuran tersimpan (tombol helper)
                                    Placeholder::make('load_measurements_hint')
                                        ->label(false)
                                        ->content(function (Get $get): HtmlString {
                                            $customerId = $get('../../customer_id');
                                            if (!$customerId) {
                                                return new HtmlString(
                                                    '<p style="font-size:13px;color:#9ca3af;">Pilih pelanggan dulu untuk bisa load ukuran yang tersimpan.</p>'
                                                );
                                            }
                                            $count = CustomerMeasurement::where('customer_id', $customerId)->count();
                                            if ($count === 0) {
                                                return new HtmlString(
                                                    '<p style="font-size:13px;color:#9ca3af;">Belum ada ukuran tim tersimpan untuk pelanggan ini.</p>'
                                                );
                                            }
                                            return new HtmlString(
                                                '<p style="font-size:13px;color:#6d28d9;font-weight:600;">💾 ' . $count . ' ukuran tim tersimpan. Isi detail di bawah, ukuran akan disimpan otomatis saat pesanan disimpan.</p>'
                                            );
                                        })
                                        ->visible(fn(Get $get) => $get('production_category') === 'custom'),

                                    // B1: Detail Ukuran Custom per Orang
                                    Section::make('Detail Ukuran Badan (per Orang)')
                                        ->description('Klik untuk buka/tutup daftar ukuran orang')
                                        ->schema([
                                            Repeater::make('detail_custom')
                                                ->label(false)
                                                ->schema([
                                                    TextInput::make('nama')
                                                        ->label('Nama')
                                                        ->required()
                                                        ->columnSpan(3),

                                                    TextInput::make('LD')
                                                        ->label('LD — Lebar Dada (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),

                                                    TextInput::make('PL')
                                                        ->label('PL — Panjang Lengan (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),

                                                    TextInput::make('LP')
                                                        ->label('LP — Lingkar Pinggang (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),

                                                    TextInput::make('LB')
                                                        ->label('LB — Lebar Bahu (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),

                                                    TextInput::make('LPi')
                                                        ->label('LPi — Lingkar Pinggul (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),

                                                    TextInput::make('PB')
                                                        ->label('PB — Panjang Baju (cm)')
                                                        ->numeric()
                                                        ->suffix('cm')
                                                        ->columnSpan(1),
                                                ])
                                                ->columns(3)
                                                ->defaultItems(1)
                                                ->addActionLabel('+ Tambah Orang')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true)
                                                ->live()
                                                ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                ->deleteAction(fn($action) => $action->after(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))),
                                            Placeholder::make('scroll_to_top_custom')
                                                ->hiddenLabel()
                                                ->content(fn() => new HtmlString(
                                                    '<div style="text-align:right;padding-top:4px;">' .
                                                    '<button type="button" ' .
                                                    'onclick="this.closest(\'.fi-fo-repeater-item\')?.scrollIntoView({behavior:\'smooth\',block:\'start\'}) ?? this.closest(\'li\')?.scrollIntoView({behavior:\'smooth\',block:\'start\'})" ' .
                                                    'style="display:inline-flex;align-items:center;gap:4px;color:#6b7280;font-size:12px;cursor:pointer;background:none;border:none;padding:4px 8px;transition:color 0.2s;" ' .
                                                    'onmouseover="this.style.color=\'#4b5563\'" onmouseout="this.style.color=\'#6b7280\'">' .
                                                    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg> ' .
                                                    'Kembali ke Atas' .
                                                    '</button>' .
                                                    '</div>'
                                                )),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'custom')
                                        ->collapsible()
                                        ->collapsed()
                                        ->compact(),

                                    // B2: Sablon/Bordir (Custom) — ukuran jadi text cmxcm
                                    Section::make('Sablon / Bordir')
                                        ->schema([
                                            Repeater::make('sablon_bordir_custom')
                                                ->label(false)
                                                ->schema([
                                                    Select::make('jenis')
                                                        ->label('Sablon/Bordir')
                                                        ->options([
                                                            'Sablon' => 'Sablon',
                                                            'Bordir' => 'Bordir',
                                                            'DTF' => 'DTF',
                                                            'Lainnya' => 'Lainnya',
                                                        ])
                                                        ->required()
                                                        ->columnSpan(2),

                                                    Select::make('lokasi')
                                                        ->label('Lokasi Titik')
                                                        ->options([
                                                            'Dada Kanan' => 'Dada Kanan',
                                                            'Dada Kiri' => 'Dada Kiri',
                                                            'Dada Kanan Nama' => 'Dada Kanan Nama',
                                                            'Dada Kiri Logo' => 'Dada Kiri Logo',
                                                            'Punggung' => 'Punggung',
                                                            'Lengan Kiri' => 'Lengan Kiri',
                                                            'Lengan Kanan' => 'Lengan Kanan',
                                                        ])
                                                        ->searchable()
                                                        ->columnSpan(6),

                                                    // ✅ REVISI: TextInput bebas (misal: 5x5 cm, A4)
                                                    TextInput::make('ukuran_cmxcm')
                                                        ->label('Ukuran (cmxcm)')
                                                        ->placeholder('cth: 5x5 cm, A4, 30x10 cm')
                                                        ->columnSpan(2),
                                                ])
                                                ->columns(10)
                                                ->defaultItems(0)
                                                ->addActionLabel('+ Tambah Varian')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'custom')
                                        ->compact(),

                                    // B3: Jumlah & Harga (Custom)
                                    Section::make('Jumlah & Harga')
                                        ->schema([
                                            Group::make([
                                                Placeholder::make('qty_custom_display')
                                                    ->label('Jumlah Baju')
                                                    ->content(function (Get $get): string {
                                                        return count($get('detail_custom') ?? []) . ' orang';
                                                    }),

                                                TextInput::make('harga_custom_satuan')
                                                    ->label('Harga Satuan per Orang')
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->dehydrated(true)
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get)),
                                            ])->columns(2),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'custom')
                                        ->compact(),

                                    // B4: Request Tambahan (Custom)
                                    Section::make('Request Tambahan')
                                        ->description('Berdasarkan jumlah baju yang dipesan')
                                        ->schema([
                                            Repeater::make('request_tambahan_custom')
                                                ->label(false)
                                                ->schema([
                                                    Select::make('jenis')
                                                        ->label('Jenis Tambahan')
                                                        ->options(static::$requestTambahanOptions)
                                                        ->searchable()
                                                        ->columnSpan(2),

                                                    TextInput::make('harga_extra_satuan')
                                                        ->label('Harga Tambahan/unit')
                                                        ->numeric()
                                                        ->prefix('Rp')
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(2),
                                                ])
                                                ->columns(4)
                                                ->defaultItems(0)
                                                ->addActionLabel('+ Tambah Request')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true)
                                                ->live()
                                                ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                ->deleteAction(fn($action) => $action->after(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'custom')
                                        ->compact(),

                                    // ═══════════════════════════════════════════════════
                                    // ── ALUR C: NON-PRODUKSI (BARANG JADI SUPPLIER) ────
                                    // ═══════════════════════════════════════════════════



                                    // C2: Sablon/Bordir (sama dengan Produksi — 2 field statis)
                                    Section::make('Sablon / Bordir')
                                        ->schema([
                                            Group::make([
                                                Select::make('np_sablon_jenis')
                                                    ->label('Teknik')
                                                    ->options([
                                                        'Sablon'   => 'Sablon',
                                                        'Bordir'   => 'Bordir',
                                                        'DTF'      => 'DTF',
                                                        'Lainnya'  => 'Lainnya',
                                                    ])
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih teknik...'),

                                                Select::make('np_sablon_lokasi')
                                                    ->label('Lokasi / Titik')
                                                    ->options(static::$lokasiSablonOptions)
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih kombinasi lokasi...'),
                                            ])->columns(2),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'non_produksi')
                                        ->compact(),

                                    // C3: Varian Ukuran (identik dengan Produksi + total summary row)
                                    Section::make('Varian Ukuran')
                                        ->schema([
                                            Repeater::make('np_varian_ukuran')
                                                ->label(false)
                                                ->schema([
                                                    Select::make('ukuran')
                                                        ->label('Ukuran')
                                                        ->options([
                                                            'XS' => 'XS', 'S' => 'S', 'M' => 'M',
                                                            'L'  => 'L',  'XL' => 'XL', 'XXL' => 'XXL', 'XXXL' => 'XXXL',
                                                        ])
                                                        ->required()
                                                        ->columnSpan(2),

                                                    TextInput::make('harga_satuan')
                                                        ->label('Harga Satuan')
                                                        ->numeric()
                                                        ->prefix('Rp')
                                                        ->required()
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(2),

                                                    TextInput::make('qty')
                                                        ->label('Kuantitas')
                                                        ->numeric()
                                                        ->required()
                                                        ->default(1)
                                                        ->minValue(1)
                                                        ->live(debounce: 500)
                                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                        ->columnSpan(2),

                                                    Placeholder::make('subtotal_np_varian')
                                                        ->label('Subtotal')
                                                        ->content(function (Get $get): string {
                                                            $h = (int) ($get('harga_satuan') ?? 0);
                                                            $q = (int) ($get('qty') ?? 0);
                                                            return 'Rp ' . number_format($h * $q, 0, ',', '.');
                                                        })
                                                        ->columnSpan(2),
                                                ])
                                                ->columns(8)
                                                ->defaultItems(0)
                                                ->addActionLabel('+ Tambah Varian')
                                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;']))
                                                ->dehydrated(true)
                                                ->live()
                                                ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))
                                                ->deleteAction(fn($action) => $action->after(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get))),

                                            // Baris total varian
                                            Placeholder::make('np_total_varian_summary')
                                                ->label(false)
                                                ->content(function (Get $get): HtmlString {
                                                    $varian = $get('np_varian_ukuran') ?? [];
                                                    $totalQty = 0;
                                                    $totalHarga = 0;
                                                    foreach ($varian as $v) {
                                                        $q = (int) ($v['qty'] ?? 0);
                                                        $h = (int) ($v['harga_satuan'] ?? 0);
                                                        $totalQty += $q;
                                                        $totalHarga += $q * $h;
                                                    }
                                                    return new HtmlString(
                                                        '<div style="display:flex;justify-content:space-between;padding:8px 4px;border-top:1px solid #e9d5ff;margin-top:4px;">'
                                                        . '<span style="font-weight:700;color:#374151;">Total</span>'
                                                        . '<span style="color:#6b7280;">' . $totalQty . ' pcs</span>'
                                                        . '<span style="font-weight:700;color:#7c3aed;">Rp ' . number_format($totalHarga, 0, ',', '.') . '</span>'
                                                        . '</div>'
                                                    );
                                                }),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'non_produksi')
                                        ->compact(),



                                    // ═══════════════════════════════════════════════════
                                    // ── ALUR D: JASA (MURNI JASA) ─────────────────────
                                    // ═══════════════════════════════════════════════════

                                    // D1: Seluruh input Jasa dalam 1 section
                                    Section::make('Detail Jasa')
                                        ->schema([
                                            // Sablon/Bordir — dalam satu section
                                            Group::make([
                                                Select::make('jasa_sablon_jenis')
                                                    ->label('Teknik Pengerjaan')
                                                    ->options([
                                                        'Sablon'  => 'Sablon',
                                                        'Bordir'  => 'Bordir',
                                                        'DTF'     => 'DTF',
                                                        'Lainnya' => 'Lainnya',
                                                    ])
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih teknik...'),

                                                Select::make('jasa_sablon_lokasi')
                                                    ->label('Lokasi / Titik')
                                                    ->options(static::$lokasiSablonOptions)
                                                    ->searchable()
                                                    ->dehydrated(true)
                                                    ->placeholder('Pilih lokasi...'),
                                            ])->columns(2),

                                            // Qty + Harga
                                            Group::make([
                                                TextInput::make('jumlah_jasa')
                                                    ->label('Jumlah')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->dehydrated(true)
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get)),

                                                TextInput::make('harga_satuan_jasa')
                                                    ->label('Harga Satuan')
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->dehydrated(true)
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcItemTotal($set, $get)),

                                                Placeholder::make('jasa_total_display')
                                                    ->label('Total')
                                                    ->content(function (Get $get): string {
                                                        $qty   = (int) ($get('jumlah_jasa') ?? 0);
                                                        $harga = (int) ($get('harga_satuan_jasa') ?? 0);
                                                        return 'Rp ' . number_format($qty * $harga, 0, ',', '.');
                                                    }),
                                            ])->columns(3),
                                        ])
                                        ->visible(fn(Get $get) => $get('production_category') === 'jasa')
                                        ->compact(),


                                            Section::make('Total yang harus dibayar')
                                                ->schema([
                                                    Group::make([
                                                        Placeholder::make('total_qty_display')
                                                            ->label('Jumlah Baju')
                                                            ->content(function (Get $get): string {
                                                                $cat = $get('production_category');
                                                                if ($cat === 'custom') {
                                                                    return count($get('detail_custom') ?? []) . ' pcs';
                                                                }
                                                                if ($cat === 'non_produksi') {
                                                                    $totalQty = 0;
                                                                    foreach ($get('np_varian_ukuran') ?? [] as $v) {
                                                                        $totalQty += (int) ($v['qty'] ?? 0);
                                                                    }
                                                                    return $totalQty . ' pcs';
                                                                }
                                                                $totalQty = 0;
                                                                foreach ($get('varian_ukuran') ?? [] as $v) {
                                                                    $totalQty += (int) ($v['qty'] ?? 0);
                                                                }
                                                                return $totalQty . ' pcs';
                                                            }),

                                                        Placeholder::make('total_item_display')
                                                            ->label('Total Biaya Tambahan')
                                                            ->content(function (Get $get): HtmlString {
                                                            $cat = $get('production_category') ?? 'produksi';
                                                            $extraTotal = 0;
                                                            if ($cat === 'custom') {
                                                                $qty = count($get('detail_custom') ?? []);
                                                                foreach ($get('request_tambahan_custom') ?? [] as $e) {
                                                                    $extraTotal += $qty * (int) ($e['harga_extra_satuan'] ?? 0);
                                                                }
                                                            } elseif ($cat === 'produksi') {
                                                                foreach ($get('request_tambahan') ?? [] as $e) {
                                                                    $extraTotal += (int) ($e['qty_tambahan'] ?? 0) * (int) ($e['harga_extra_satuan'] ?? 0);
                                                                }
                                                            }
                                                            $color = $extraTotal > 0 ? '#059669' : '#9ca3af';
                                                            return new HtmlString(
                                                                '<span style="font-weight:600;color:' . $color . ';font-size:14px;">Rp '
                                                                . number_format($extraTotal, 0, ',', '.') . '</span>'
                                                            );
                                                        }),
                                                    ])->columns(2),
                                                    Placeholder::make('total_extras_display')
                                                        ->label('Total Harga Baju')
                                                        ->content(function (Get $get): HtmlString {
                                                                $total = static::calcItemTotal($get);
                                                                return new HtmlString(
                                                                    '<span style="font-weight:700;color:#7c3aed;font-size:16px;">Rp '
                                                                    . number_format($total, 0, ',', '.') . '</span>'
                                                                );
                                                            }),
                                                ])
                                                ->visible(fn(Get $get) => $get('production_category') !== 'jasa')
                                                ->compact(),

                                            // Tombol balik ke atas — scroll ke header item agar bisa klik title untuk tutup
                                            Placeholder::make('close_item')
                                                ->hiddenLabel()
                                                ->content(fn() => new HtmlString(
                                                    '<div style="text-align:right;padding-top:8px;">' .
                                                    '<button type="button" ' .
                                                    'onclick="this.closest(\'.fi-fo-repeater-item\')?.scrollIntoView({behavior:\'smooth\',block:\'start\'}) ?? this.closest(\'li\')?.scrollIntoView({behavior:\'smooth\',block:\'start\'})" ' .
                                                    'style="display:inline-flex;align-items:center;gap:4px;color:#6b7280;font-size:12px;cursor:pointer;background:none;border:none;padding:4px 8px;transition:color 0.2s;" ' .
                                                    'onmouseover="this.style.color=\'#4b5563\'" ' .
                                                    'onmouseout="this.style.color=\'#6b7280\'">' .
                                                    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg> ' .
                                                    'Kembali ke Atas' .
                                                    '</button>' .
                                                    '</div>'
                                                )),
                                ])
                                ->columns(1)
                                ->defaultItems(0)
                                ->addActionLabel('+ Tambah Produk')
                                ->addAction(fn($action) => $action
                                    ->color('primary')
                                    ->extraAttributes(['style' => 'width:100%;justify-content:center;color:#7F00FF;border-color:#7F00FF;background:#F3E8FF;font-weight:600;']))
                                // ✅ REVISI 4: Card view — collapsed setelah item diisi
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(function (array $state): ?string {
                                    $cat   = $state['production_category'] ?? 'produksi';
                                    $name  = $state['product_name'] ?? 'Produk Baru';
                                    if (!$name) $name = 'Produk Baru';
                                    $total = static::calcItemTotalFromArray($state);

                                    if ($cat === 'custom') {
                                        $qty = count($state['detail_custom'] ?? []);
                                        return "[Custom] {$qty}x {$name} — Rp " . number_format($total, 0, ',', '.');
                                    }

                                    if ($cat === 'non_produksi') {
                                        $totalQty = 0;
                                        foreach ($state['np_varian_ukuran'] ?? [] as $v) {
                                            $totalQty += (int) ($v['qty'] ?? 0);
                                        }
                                        $supplierProduct = ($state['supplier_product'] ?? null) ?: $name;
                                        return "[Non-Produksi] {$totalQty}x {$supplierProduct} — Rp " . number_format($total, 0, ',', '.');
                                    }

                                    if ($cat === 'jasa') {
                                        $qty      = (int) ($state['jumlah_jasa'] ?? 0);
                                        $namaJasa = ($state['nama_jasa'] ?? null) ?: 'Jasa Baru';
                                        return "[Jasa] {$qty}x {$namaJasa} — Rp " . number_format($total, 0, ',', '.');
                                    }

                                    // produksi (default)
                                    $totalQty = 0;
                                    foreach ($state['varian_ukuran'] ?? [] as $v) {
                                        $totalQty += (int) ($v['qty'] ?? 0);
                                    }
                                    return "[Produksi] {$totalQty}x {$name} — Rp " . number_format($total, 0, ',', '.');
                                })
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                ->deleteAction(
                                    fn($action) => $action->after(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                )
                                ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => static::mutateItemData($data))
                                ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => static::mutateItemData($data))
                                ->mutateRelationshipDataBeforeFillUsing(fn(array $data) => static::unmutateItemData($data)),
                        ]),

                    // Section 4: Pembayaran
                    Section::make('Pembayaran')
                        ->schema([
                            Group::make([
                                TextInput::make('subtotal')
                                    ->label('Subtotal Biaya')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),

                                TextInput::make('tax')
                                    ->label('PPN 11%')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),
                            ])->columns(2),

                            Group::make([
                                TextInput::make('shipping_cost')
                                    ->label('Ongkos Kirim')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),

                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),
                            ])->columns(2),

                            TextInput::make('total_price')
                                ->label('Total')
                                ->numeric()
                                ->prefix('Rp')
                                ->disabled()
                                ->extraInputAttributes(['style' => 'font-size: 1.25rem; font-weight: bold; color: #7e22ce;'])
                                ->dehydrated()
                                ->default(0)
                                ->columnSpanFull(),

                            Group::make([
                                TextInput::make('down_payment')
                                    ->label('DP')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(),

                                Placeholder::make('remaining_payment')
                                    ->label('Sisa Tagihan')
                                    ->content(function (Get $get): HtmlString {
                                        $total = (int) $get('total_price') ?? 0;
                                        $dp = (int) $get('down_payment') ?? 0;
                                        $remaining = $total - $dp;
                                        $color = $remaining > 0 ? 'text-danger-600' : 'text-success-600';
                                        return new HtmlString(
                                            '<span class="font-bold ' . $color . '" style="font-size:20px;">'
                                            . 'Rp ' . number_format($remaining, 0, ',', '.')
                                            . '</span>'
                                        );
                                    }),
                            ])->columns(2),

                            FileUpload::make('dp_proof')
                                ->label('Bukti Pembayaran DP')
                                ->image()
                                ->imageEditor()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->maxSize(5120)
                                ->directory('dp-proofs')
                                ->visibility('private')
                                ->downloadable()
                                ->openable()
                                ->columnSpanFull()
                                ->helperText('Upload foto/scan bukti transfer DP (maks. 5MB)'),
                        ]),
                ])
                    ->columnSpan(2),

                // RIGHT SIDE — Ringkasan Pesanan (Sticky Sidebar)
                Group::make([
                    Section::make('Ringkasan Pesanan')
                        ->schema([
                            Placeholder::make('summary_full')
                                ->live()
                                ->label(false)
                                ->content(function (Get $get): HtmlString {
                                    $items = $get('orderItems') ?? [];
                                    $subtotal = (int) ($get('subtotal') ?? 0);
                                    $shipping = (int) ($get('shipping_cost') ?? 0);
                                    $tax = (int) ($get('tax') ?? 0);
                                    $discount = (int) ($get('discount') ?? 0);
                                    $total = (int) ($get('total_price') ?? 0);
                                    $dp = (int) ($get('down_payment') ?? 0);
                                    $remaining = $total - $dp;

                                    $fmt = fn(int $v) => 'Rp ' . number_format($v, 0, ',', '.');

                                    $itemsHtml = '';
                                    $hasItems = false;
                                    foreach ($items as $item) {
                                        if (!empty($item['product_name'])) {
                                            $hasItems = true;
                                            $cat = $item['production_category'] ?? 'produksi';
                                            $name = htmlspecialchars($item['product_name']);
                                            $itemTotal = static::calcItemTotalFromArray($item);

                                            $badgeStyle = 'background:#f3e8ff;color:#7c3aed;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap;';
                                            if ($cat === 'custom') {
                                                $itemQty = count($item['detail_custom'] ?? []);
                                                $badgeLabel = 'Custom';
                                            } elseif ($cat === 'non_produksi') {
                                                $itemQty = 0;
                                                foreach ($item['np_varian_ukuran'] ?? [] as $v) {
                                                    $itemQty += (int) ($v['qty'] ?? 0);
                                                }
                                                $badgeLabel = 'Non-Produksi';
                                            } elseif ($cat === 'jasa') {
                                                $itemQty = (int) ($item['jumlah_jasa'] ?? 0);
                                                $badgeLabel = 'Jasa';
                                            } else {
                                                // produksi (default)
                                                $itemQty = 0;
                                                foreach ($item['varian_ukuran'] ?? [] as $v) {
                                                    $itemQty += (int) ($v['qty'] ?? 0);
                                                }
                                                $badgeLabel = 'Produksi';
                                            }
                                            $badge = '<span style="' . $badgeStyle . '">' . $badgeLabel . '</span>';

                                            $itemsHtml .= '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e9d5ff;border-radius:10px;background:#faf5ff;margin-bottom:8px;">';
                                            $itemsHtml .= $badge;
                                            $itemsHtml .= '<div style="flex:1;min-width:0;">';
                                            $itemsHtml .= '<div style="font-size:13px;font-weight:500;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . $itemQty . 'x ' . $name . '</div>';
                                            $itemsHtml .= '<div style="font-size:12px;color:#7c3aed;">' . $fmt($itemTotal) . '</div>';
                                            $itemsHtml .= '</div></div>';
                                        }
                                    }
                                    if (!$hasItems) {
                                        $itemsHtml = '<p style="color:#9ca3af;font-size:13px;">Belum ada produk ditambahkan</p>';
                                    }

                                    $row = fn(string $label, string $value, bool $purple = false, bool $bold = false) =>
                                        '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">'
                                        . '<span style="color:#6b7280;font-size:14px;">' . $label . '</span>'
                                        . '<span style="font-size:14px;' . ($purple ? 'color:#7c3aed;' : 'color:#374151;') . ($bold ? 'font-weight:700;' : '') . '">' . $value . '</span>'
                                        . '</div>';

                                    $divider = '<hr style="border:none;border-top:1px solid #e5e7eb;margin:8px 0;">';

                                    $remainingHtml = $remaining <= 0
                                        ? '<span style="background:#22c55e;color:white;font-size:13px;font-weight:700;padding:3px 12px;border-radius:20px;">Lunas</span>'
                                        : '<span style="color:#7c3aed;font-weight:700;font-size:14px;">' . $fmt($remaining) . '</span>';

                                    $html = '<div style="font-family:inherit;">' . $itemsHtml;
                                    $html .= '<div style="margin-top:12px;">';
                                    $html .= $row('Subtotal', $fmt($subtotal), true, true);
                                    $html .= $row('Ongkos Kirim', $fmt($shipping));
                                    $html .= $row('PPn 11%', $fmt($tax));
                                    $html .= $row('Diskon', $fmt($discount));
                                    $html .= $divider;
                                    $html .= $row('Total', $fmt($total), true, true);
                                    $html .= $row('DP', $fmt($dp));
                                    $html .= $divider;
                                    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">';
                                    $html .= '<span style="color:#6b7280;font-size:14px;">Sisa</span>';
                                    $html .= $remainingHtml;
                                    $html .= '</div></div></div>';

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->extraAttributes(['style' => 'position:sticky;']),
                ])
                    ->columnSpan(1)
                    ->extraAttributes(['style' => 'align-self:flex-start;position:sticky;top:5rem;']),
            ])
            ->columns(3);
    }

    // ─── Hitung total satu item dari Get $get (saat live form) ──────────────
    protected static function calcItemTotal(Get $get): int
    {
        $cat = $get('production_category') ?? 'produksi';

        if ($cat === 'custom') {
            $qty = count($get('detail_custom') ?? []);
            $harga = (int) ($get('harga_custom_satuan') ?? 0);
            $extras = $get('request_tambahan_custom') ?? [];
            $extraSum = 0;
            foreach ($extras as $e) {
                $extraSum += (int) ($e['harga_extra_satuan'] ?? 0);
            }
            return $qty * ($harga + $extraSum);
        }

        if ($cat === 'non_produksi') {
            $totalHarga = 0;
            foreach ($get('np_varian_ukuran') ?? [] as $v) {
                $totalHarga += (int) ($v['qty'] ?? 0) * (int) ($v['harga_satuan'] ?? 0);
            }
            return $totalHarga;
        }

        if ($cat === 'jasa') {
            $qty   = (int) ($get('jumlah_jasa') ?? 0);
            $harga = (int) ($get('harga_satuan_jasa') ?? 0);
            return $qty * $harga;
        }

        // produksi (default)
        $totalHarga = 0;
        foreach ($get('varian_ukuran') ?? [] as $v) {
            $totalHarga += (int) ($v['qty'] ?? 0) * (int) ($v['harga_satuan'] ?? 0);
        }
        foreach ($get('request_tambahan') ?? [] as $e) {
            $totalHarga += (int) ($e['qty_tambahan'] ?? 0) * (int) ($e['harga_extra_satuan'] ?? 0);
        }
        return $totalHarga;
    }

    // ─── Hitung total satu item dari array $state (card label & sidebar) ─────
    protected static function calcItemTotalFromArray(array $state): int
    {
        $cat = $state['production_category'] ?? 'produksi';

        if ($cat === 'custom') {
            $qty = count($state['detail_custom'] ?? []);
            $harga = (int) ($state['harga_custom_satuan'] ?? 0);
            $extras = $state['request_tambahan_custom'] ?? [];
            $extraSum = 0;
            foreach ($extras as $e) {
                $extraSum += (int) ($e['harga_extra_satuan'] ?? 0);
            }
            return $qty * ($harga + $extraSum);
        }

        if ($cat === 'non_produksi') {
            $totalHarga = 0;
            foreach ($state['np_varian_ukuran'] ?? [] as $v) {
                $totalHarga += (int) ($v['qty'] ?? 0) * (int) ($v['harga_satuan'] ?? 0);
            }
            return $totalHarga;
        }

        if ($cat === 'jasa') {
            $qty   = (int) ($state['jumlah_jasa'] ?? 0);
            $harga = (int) ($state['harga_satuan_jasa'] ?? 0);
            return $qty * $harga;
        }

        // produksi (default)
        $totalHarga = 0;
        foreach ($state['varian_ukuran'] ?? [] as $v) {
            $totalHarga += (int) ($v['qty'] ?? 0) * (int) ($v['harga_satuan'] ?? 0);
        }
        foreach ($state['request_tambahan'] ?? [] as $e) {
            $totalHarga += (int) ($e['qty_tambahan'] ?? 0) * (int) ($e['harga_extra_satuan'] ?? 0);
        }
        return $totalHarga;
    }

    // ─── Live recalc price + quantity satu item ─────────────────────────────────
    protected static function recalcItemTotal(Set $set, Get $get): void
    {
        $cat = $get('production_category') ?? 'produksi';

        if ($cat === 'custom') {
            $qty = count($get('detail_custom') ?? []);
        } elseif ($cat === 'non_produksi') {
            $qty = 0;
            foreach ($get('np_varian_ukuran') ?? [] as $v) {
                $qty += (int) ($v['qty'] ?? 0);
            }
        } elseif ($cat === 'jasa') {
            $qty = (int) ($get('jumlah_jasa') ?? 0);
        } else {
            $qty = 0;
            foreach ($get('varian_ukuran') ?? [] as $v) {
                $qty += (int) ($v['qty'] ?? 0);
            }
        }

        $total = static::calcItemTotal($get);
        $set('quantity', max(1, $qty));
        $set('price', $total > 0 && $qty > 0 ? intdiv($total, $qty) : 0);

        static::updateSubtotal($set, $get);
    }

    // ─── Mutate sebelum simpan ke DB: pack JSON + simpan measurements ─────────
    protected static function mutateItemData(array $data): array
    {
        $cat = $data['production_category'] ?? 'produksi';

        if ($cat === 'custom') {
            $detailCustom = $data['detail_custom'] ?? [];
            $qty = count($detailCustom);
            $harga = (int) ($data['harga_custom_satuan'] ?? 0);
            $extras = $data['request_tambahan_custom'] ?? [];
            $extraSum = array_sum(array_map(fn($e) => (int) ($e['harga_extra_satuan'] ?? 0), $extras));
            $total = $qty * ($harga + $extraSum);

            $data['size_and_request_details'] = [
                'category'         => 'custom',
                'bahan'            => $data['bahan_baju'] ?? null,
                'detail_custom'    => $detailCustom,
                'sablon_bordir'    => $data['sablon_bordir_custom'] ?? [],
                'request_tambahan' => $extras,
                'harga_satuan'     => $harga,
            ];
            $data['quantity'] = max(1, $qty);
            $data['price']    = $qty > 0 ? intdiv($total, $qty) : 0;

        } elseif ($cat === 'non_produksi') {
            $varianNp = $data['np_varian_ukuran'] ?? [];
            $totalQty = (int) array_sum(array_map(fn($v) => (int) ($v['qty'] ?? 0), $varianNp));
            $totalHarga = 0;
            foreach ($varianNp as $v) {
                $totalHarga += (int) ($v['qty'] ?? 0) * (int) ($v['harga_satuan'] ?? 0);
            }

            $data['size_and_request_details'] = [
                'category'          => 'non_produksi',
                'supplier_product'  => $data['supplier_product'] ?? null,
                'sablon_jenis'      => $data['np_sablon_jenis'] ?? null,
                'sablon_lokasi'     => $data['np_sablon_lokasi'] ?? null,
                'varian_ukuran'     => $varianNp,
            ];
            $data['quantity'] = max(1, $totalQty);
            $data['price']    = $totalQty > 0 ? intdiv($totalHarga, $totalQty) : 0;

        } elseif ($cat === 'jasa') {
            $qty   = (int) ($data['jumlah_jasa'] ?? 0);
            $harga = (int) ($data['harga_satuan_jasa'] ?? 0);

            $data['size_and_request_details'] = [
                'category'        => 'jasa',
                'jumlah'          => $qty,
                'harga_satuan'    => $harga,
                'sablon_jenis'    => $data['jasa_sablon_jenis'] ?? null,
                'sablon_lokasi'   => $data['jasa_sablon_lokasi'] ?? null,
            ];
            $data['quantity'] = max(1, $qty);
            $data['price']    = $harga;

        } else {
            // produksi (default)
            $totalQty = 0;
            $totalHarga = 0;
            foreach ($data['varian_ukuran'] ?? [] as $v) {
                $q = (int) ($v['qty'] ?? 0);
                $h = (int) ($v['harga_satuan'] ?? 0);
                $totalQty   += $q;
                $totalHarga += $q * $h;
            }
            $extras = $data['request_tambahan'] ?? [];
            foreach ($extras as $e) {
                $totalHarga += (int) ($e['qty_tambahan'] ?? 0) * (int) ($e['harga_extra_satuan'] ?? 0);
            }

            $data['size_and_request_details'] = [
                'category'         => 'produksi',
                'bahan'            => $data['bahan_baju'] ?? null,
                'sablon_jenis'     => $data['sablon_jenis'] ?? null,
                'sablon_lokasi'    => $data['sablon_lokasi'] ?? null,
                'varian_ukuran'    => $data['varian_ukuran'] ?? [],
                'request_tambahan' => $extras,
            ];
            $data['quantity'] = max(1, $totalQty);
            $data['price']    = $totalQty > 0 ? intdiv($totalHarga, $totalQty) : 0;
        }

        // Bersihkan semua virtual fields sebelum simpan
        // CATATAN: Filament butuh `$data['id']` dsb untuk menghapus (kalau ada)
        unset(
            $data['bahan_baju'],
            $data['sablon_jenis'],       $data['sablon_lokasi'],
            $data['sablon_bordir'],
            $data['sablon_bordir_custom'],
            $data['varian_ukuran'],
            $data['request_tambahan'],   $data['request_tambahan_custom'],
            $data['detail_custom'],
            $data['harga_custom_satuan'],
            // Non-Produksi
            $data['supplier_product'],
            $data['np_sablon_jenis'],    $data['np_sablon_lokasi'],
            $data['np_varian_ukuran'],
            $data['np_request_tambahan'],
            // Jasa
            $data['nama_jasa'],
            $data['jumlah_jasa'],        $data['harga_satuan_jasa'],
            $data['jasa_sablon_jenis'],  $data['jasa_sablon_lokasi'],
            $data['jasa_total_display'],
            // Display fields
            $data['total_item_display'],
            $data['total_qty_display'],
            $data['total_varian_summary'],
            $data['qty_custom_display'],
            $data['load_measurements_hint'],
            $data['qty_tambahan_error'],
            $data['subtotal_np_varian'],
        );

        return $data;
    }

    // ─── Unpack JSON data ke virtual field form (Edit mode) ───────────────────
    public static function unmutateItemData(array $data): array
    {
        $details = $data['size_and_request_details'] ?? [];
        if (empty($details)) {
            return $data;
        }

        $cat = $details['category'] ?? ($data['production_category'] ?? 'produksi');
        $data['production_category'] = $cat;

        // PASTIKAN ID TIDAK HILANG AGAR BISA DI-DELETE/UPDATE
        if (!isset($data['id']) && isset($data['record_id'])) {
             $data['id'] = $data['record_id'];
        }

        if ($cat === 'custom') {
            $data['bahan_baju']              = $details['bahan'] ?? null;
            $data['detail_custom']           = $details['detail_custom'] ?? [];
            $data['sablon_bordir_custom']    = $details['sablon_bordir'] ?? [];
            $data['request_tambahan_custom'] = $details['request_tambahan'] ?? [];
            $data['harga_custom_satuan']     = $details['harga_satuan'] ?? 0;

        } elseif ($cat === 'non_produksi') {
            $data['supplier_product']    = $details['supplier_product'] ?? null;
            $data['np_sablon_jenis']     = $details['sablon_jenis'] ?? null;
            $data['np_sablon_lokasi']    = $details['sablon_lokasi'] ?? null;
            $data['np_varian_ukuran']    = $details['varian_ukuran'] ?? [];

        } elseif ($cat === 'jasa') {
            $data['jumlah_jasa']        = $details['jumlah'] ?? 0;
            $data['harga_satuan_jasa']  = $details['harga_satuan'] ?? 0;
            $data['jasa_sablon_jenis']  = $details['sablon_jenis'] ?? null;
            $data['jasa_sablon_lokasi'] = $details['sablon_lokasi'] ?? null;

        } else {
            // produksi (default)
            $data['bahan_baju']       = $details['bahan'] ?? null;
            $data['sablon_jenis']     = $details['sablon_jenis'] ?? null;
            $data['sablon_lokasi']    = $details['sablon_lokasi'] ?? null;
            $data['varian_ukuran']    = $details['varian_ukuran'] ?? [];
            $data['request_tambahan'] = $details['request_tambahan'] ?? [];
        }

        return $data;
    }

    // Alias for EditOrder page
    public static function unmutateOrderItemData(array $data): array
    {
        return static::unmutateItemData($data);
    }

    // ─── Update subtotal pesanan (sum semua items) ────────────────────────────
    protected static function updateSubtotal(Set $set, Get $get): void
    {
        $items = $get('orderItems') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            // Hitung langsung dari data item (bukan qty×price yang bisa stale/inaccurate)
            $subtotal += static::calcItemTotalFromArray($item);
        }
        $set('subtotal', $subtotal);
        static::updateTotalPrice($set, $get);
    }

    protected static function updateTotalPrice(Set $set, Get $get): void
    {
        $subtotal = (int) ($get('subtotal') ?? 0);
        $tax = (int) ($get('tax') ?? 0);
        $shipping = (int) ($get('shipping_cost') ?? 0);
        $discount = (int) ($get('discount') ?? 0);
        $set('total_price', max(0, $subtotal + $tax + $shipping - $discount));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'diterima' => 'gray',
                        'antrian' => 'warning',
                        'diproses' => 'info',
                        'selesai' => 'success',
                        'siap_diambil' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
