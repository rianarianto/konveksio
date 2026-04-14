<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Models\Customer;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use App\Models\Material;
use App\Models\Product;
use App\Models\AddonOption;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'Pesanan';
    protected static ?string $pluralModelLabel = 'Daftar Pesanan';


    protected static string|\UnitEnum|null $navigationGroup = 'PENJUALAN';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->role === 'owner';
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->role === 'owner';
    }

    protected static bool $isScopedToTenant = true;

    // ΓöÇΓöÇΓöÇ Bahan baju dari Master Data ΓöÇΓöÇΓöÇ
    public static function getBahanOptions(): array
    {
        $tenantId = Filament::getTenant()?->id;
        if (!$tenantId)
            return [];

        $materials = Material::where('shop_id', $tenantId)->get();
        $options = [];
        foreach ($materials as $material) {
            $hex = $material->color_code ?: '#e5e7eb';
            $label = $material->name . ($material->type ? " ({$material->type})" : '');

            $stockInfo = '';
            if ($material->current_stock > 0) {
                $stockInfo = ' <small style="color:#7c3aed;font-weight:700;margin-left:4px;">(Stok: ' . $material->current_stock . ' ' . $material->unit . ')</small>';
            }

            $options[$material->id] = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                . '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' . $hex . ';border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
                . '<span>' . htmlspecialchars($label) . $stockInfo . '</span>'
                . '</span>';
        }
        return $options;
    }

    // ΓöÇΓöÇΓöÇ Supplier produk dari Master Data ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    public static function getSupplierProductOptions(): array
    {
        $tenantId = Filament::getTenant()?->id;
        if (!$tenantId)
            return [];

        $products = Product::where('shop_id', $tenantId)->withSum('variants', 'stock')->get();
        $options = [];
        foreach ($products as $product) {
            $hex = $product->color_code ?: '#e5e7eb';
            $label = $product->name . ($product->type ? " ({$product->type})" : '');

            $totalStock = (int) ($product->variants_sum_stock ?? 0);
            $stockInfo = '';
            if ($totalStock > 0) {
                $stockInfo = ' <small style="color:#7c3aed;font-weight:700;margin-left:4px;">(Stok: ' . $totalStock . ' pcs)</small>';
            }

            $options[$product->id] = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                . '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' . $hex . ';border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
                . '<span>' . htmlspecialchars($label) . $stockInfo . '</span>'
                . '</span>';
        }
        return $options;
    }

    // Size dari Master Data
    public static function getStoreSizeOptions(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->id;
        if (!$tenantId)
            return [];

        return \App\Models\StoreSize::where('shop_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'name')
            ->toArray();
    }

    // ΓöÇΓöÇΓöÇ Request tambahan dari Master Data ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    public static function getRequestTambahanOptions(): array
    {
        $tenantId = Filament::getTenant()?->id;
        if (!$tenantId)
            return [];

        return AddonOption::where('shop_id', $tenantId)
            ->where('is_active', true)
            ->pluck('name', 'name')
            ->toArray();
    }

    // Lokasi sablon/bordir
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

                            // Express
                            Toggle::make('is_express')
                                ->label('Pesanan Express')
                                ->onIcon('heroicon-m-bolt')
                                ->helperText('Pesanan ini akan diprioritaskan di antrian produksi')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get))
                                ->onColor('danger')
                                ->columnSpan(2),

                            TextInput::make('express_fee')
                                ->label('Biaya Express (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('0')
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get))
                                ->visible(fn(Get $get): bool => (bool) $get('is_express'))
                                ->helperText('Biaya tambahan untuk layanan express')
                                ->columnSpan(1),
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
                                        . 'Buka di Google Maps</a>'
                                    );
                                })
                                ->columnSpan('full'),
                        ])
                        ->columns(2),

                    // Section 3: Spreadsheet Produk (TABEL STATELESS)
                    Section::make('Daftar Produk / Item Pesanan')
                        ->description('Input produk pesanan di sini secara fleksibel. Klik "Simpan" di akhir untuk memasukkan ke database.')
                        ->icon('heroicon-o-table-cells')
                        ->schema([
                            // Hidden field to store JSON payload from spreadsheet
                            Hidden::make('order_items_payload')
                                ->id('order_items_payload')
                                ->live(),

                            \Filament\Schemas\Components\Livewire::make(\App\Livewire\OrderItemsSpreadsheet::class, function (Get $get, ?Order $record) {
                                // For Edit mode, we pre-fill from database if payload is empty
                                $items = [];
                                if ($record) {
                                    $items = $record->orderItems()->get()->toArray();
                                }
                                
                                return [
                                    'items' => $items,
                                    'orderId' => $record?->id,
                                ];
                            })
                            ->key('items-spreadsheet'),
                        ])
                        ->collapsible(),

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
                                    ->placeholder('0'),

                                TextInput::make('tax')
                                    ->label('PPN 11%')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),
                            ])->columns(2),

                            Group::make([
                                TextInput::make('shipping_cost')
                                    ->label('Ongkos Kirim')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),

                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::updateTotalPrice($set, $get)),
                            ])->columns(2),

                            TextInput::make('total_price')
                                ->label('Total Tagihan')
                                ->numeric()
                                ->prefix('Rp')
                                ->disabled()
                                ->extraInputAttributes(['style' => 'font-size: 1.25rem; font-weight: bold; color: #7e22ce;'])
                                ->dehydrated()
                                ->placeholder('0')
                                ->columnSpanFull(),

                            Section::make('Pembayaran Awal / DP')
                                ->description('Input pembayaran awal saat pembuatan pesanan. Pembayaran tambahan bisa dikelola di tabel paling bawah setelah pesanan disimpan.')
                                ->schema([
                                    Group::make([
                                        TextInput::make('initial_payment_amount')
                                            ->label('Nominal Bayar (DP)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->placeholder('0')
                                            ->live()
                                            ->dehydrated(false) // Virtual field
                                            ->helperText('Kosongkan jika belum ada pembayaran'),

                                        Select::make('initial_payment_method')
                                            ->label('Metode Pembayaran')
                                            ->options([
                                                'cash' => 'Cash',
                                                'transfer' => 'Transfer',
                                                'qris' => 'QRIS',
                                            ])
                                            ->default('cash')
                                            ->dehydrated(false), // Virtual field
                                    ])->columns(2),

                                    FileUpload::make('initial_payment_proof')
                                        ->label('Bukti Bayar')
                                        ->image()
                                        ->disk('public')
                                        ->directory('payments/proofs')
                                        ->dehydrated(false) // Virtual field
                                        ->openable()
                                        ->downloadable()
                                        ->previewable()
                                        ->getUploadedFileUsing(function (string $file): ?array {
                                            $disk = Storage::disk('public');
                                            if (!$disk->exists($file)) {
                                                return null;
                                            }
                                            return [
                                                'name' => basename($file),
                                                'size' => $disk->size($file),
                                                'type' => (function () use ($disk, $file) {
                                                    $path = $disk->path($file);
                                                    if (!file_exists($path))
                                                        return 'image/jpeg';
                                                    return mime_content_type($path) ?: 'image/jpeg';
                                                })(),
                                                'url' => asset('storage/' . $file),
                                            ];
                                        })
                                        ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                            $mimeType = $file->getMimeType();

                                            // PDF - simpan langsung tanpa kompres
                                            if ($mimeType === 'application/pdf') {
                                                $filename = Str::uuid() . '.pdf';
                                                $path = 'payments/proofs/' . $filename;
                                                Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
                                                return $path;
                                            }

                                            // Gambar - kompres dengan Intervention Image
                                            $img = Image::read($file->getRealPath());

                                            // Resize jika lebar > 1920px (pertahankan aspek rasio)
                                            if ($img->width() > 1920) {
                                                $img->scaleDown(width: 1920);
                                            }

                                            // Encode ke JPEG quality 75
                                            $encoded = $img->toJpeg(quality: 75);

                                            $filename = Str::uuid() . '.jpg';
                                            $path = 'payments/proofs/' . $filename;
                                            Storage::disk('public')->put($path, (string) $encoded);

                                            return $path;
                                        }),

                                    Placeholder::make('remaining_payment_display')
                                        ->label('Sisa Yang Harus Dibayar')
                                        ->content(function (Get $get, ?Order $record): HtmlString {
                                            if ($record) {
                                                $record->refresh();
                                            }
                                            $total = (int) ($get('total_price') ?? 0);
                                            $initial = (int) ($get('initial_payment_amount') ?? 0);
                                            // Jika sedang edit, total paid dari database + initial yang sedang diinput (jika baru)
                                            // Namun record pembayaran pertama biasanya sudah masuk ke initial_payment_amount saat fill data
                                            // Jadi kita hitung dari total - initial (virtual)
                                
                                            $remaining = $total - $initial;
                                            $color = $remaining > 0 ? '#ef4444' : '#22c55e';
                                            $text = $remaining > 0 ? 'Rp ' . number_format($remaining, 0, ',', '.') : 'Lunas';

                                            return new HtmlString('<span style="font-size:18px; font-weight:800; color:' . $color . '">' . $text . '</span>');
                                        })
                                ])
                                ->compact()
                                ->collapsible(),

                        ]),
                ])
                    ->columnSpan(['lg' => 2, 'default' => 1]),

                // SIDEBAR - Ringkasan Pesanan
                Group::make([
                    Section::make('Status & Ringkasan')
                        ->schema([
                            Select::make('status')
                                ->label('Status Pesanan')
                                ->options([
                                    'draft' => 'Draf (Belum Konfirmasi)',
                                    'diterima' => 'Diterima',
                                    'antrian' => 'Antrian',
                                    'diproses' => 'Diproses',
                                    'selesai' => 'Selesai',
                                    'siap_diambil' => 'Siap Diambil',
                                ])
                                ->required()
                                ->selectablePlaceholder(false)
                                ->native(false)
                                ->prefixIcon('heroicon-o-flag'),

                            Placeholder::make('summary_full')
                                ->live()
                                ->label(false)
                                ->content(function (Get $get, ?Order $record): HtmlString {
                                    $fmt = fn(int $v) => 'Rp ' . number_format($v, 0, ',', '.');
                                    // READ FROM SPREADSHEET PAYLOAD (STATELASS/JSON)
                                    $payload = $get('order_items_payload');
                                    $itemsState = $payload ? json_decode($payload, true) : [];
                                    
                                    $subtotal = 0;
                                    $itemsHtml = '';
                                    $hasItems = false;
                                    
                                    foreach ($itemsState as $item) {
                                        $name = htmlspecialchars($item['product_name'] ?? 'Pilih Produk...');
                                        $price = (int) ($item['price'] ?? 0);
                                        $qty = (int) ($item['quantity'] ?? 1);
                                        $itemTotal = $price * $qty;
                                        $subtotal += $itemTotal;
                                        $hasItems = true;
                                        $cat = $item['production_category'] ?? 'produksi';
                                        
                                        $badgeStyle = 'background:#f3e8ff;color:#7c3aed;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap;';
                                        $badgeLabel = match ($cat) {
                                            'custom' => 'Custom',
                                            'non_produksi' => 'Non-Produksi',
                                            'jasa' => 'Jasa',
                                            default => 'Produksi',
                                        };
                                        $badge = '<span style="' . $badgeStyle . '">' . $badgeLabel . '</span>';
                                        
                                        $sizeLabel = ($item['size'] ?? null) ? ' (' . htmlspecialchars($item['size']) . ')' : '';
                                        $itemsHtml .= '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e9d5ff;border-radius:10px;background:#faf5ff;margin-bottom:8px;">';
                                        $itemsHtml .= $badge;
                                        $itemsHtml .= '<div style="flex:1;min-width:0;">';
                                        $itemsHtml .= '<div style="font-size:13px;font-weight:500;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . $qty . 'x ' . $name . $sizeLabel . '</div>';
                                        $itemsHtml .= '<div style="font-size:12px;color:#7c3aed;">' . $fmt($itemTotal) . '</div>';
                                        $itemsHtml .= '</div></div>';
                                    }

                                    if (!$hasItems) {
                                        $itemsHtml = '<p style="color:#9ca3af;font-size:13px;">Belum ada produk ditambahkan</p>';
                                    }

                                    $shipping = (int) ($get('shipping_cost') ?? 0);
                                    $tax = (int) ($get('tax') ?? 0);
                                    $discount = (int) ($get('discount') ?? 0);
                                    $isExpress = (bool) $get('is_express');
                                    $expressFeeVal = (int) ($get('express_fee') ?? 0);
                                    
                                    $total = max(0, $subtotal + $tax + $shipping + ($isExpress ? $expressFeeVal : 0) - $discount);

                                    // Hitung total bayar (jika edit)
                                    $totalPaid = 0;
                                    $paymentsHtml = '';
                                    if ($record) {
                                        $payments = $record->payments()->orderBy('payment_date', 'asc')->get();
                                        $totalPaid = (int) $payments->sum('amount');

                                        foreach ($payments as $index => $pay) {
                                            $methodLabel = match ($pay->payment_method) {
                                                'transfer' => 'TF',
                                                'qris' => 'QRIS',
                                                default => 'Cash',
                                            };
                                            $paymentsHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">'
                                                . '<span style="color:#6b7280;font-size:13px;">Bayar #' . ($index + 1) . ' (' . $methodLabel . ')</span>'
                                                . '<span style="font-size:13px;color:#374151;">' . $fmt($pay->amount) . '</span>'
                                                . '</div>';
                                        }
                                    } else {
                                        $initial = (int) ($get('initial_payment_amount') ?? 0);
                                        $totalPaid = $initial;
                                    }

                                    $remaining = $total - $totalPaid;
                                    $row = fn(string $label, string $value, bool $purple = false, bool $bold = false) =>
                                        '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">'
                                        . '<span style="color:#6b7280;font-size:14px;">' . $label . '</span>'
                                        . '<span style="font-size:14px;' . ($purple ? 'color:#7c3aed;' : 'color:#374151;') . ($bold ? 'font-weight:700;' : '') . '">' . $value . '</span>'
                                        . '</div>';

                                    $divider = '<hr style="border:none;border-top:1px solid #e5e7eb;margin:8px 0;">';

                                    $remainingLabel = $remaining <= 0
                                        ? '<span style="background:#22c55e;color:white;font-size:13px;font-weight:700;padding:3px 12px;border-radius:20px;">Lunas</span>'
                                        : '<span style="color:#7c3aed;font-weight:700;font-size:14px;">' . $fmt($remaining) . '</span>';

                                    $html = '<div style="font-family:inherit;">' . $itemsHtml;
                                    $html .= '<div style="margin-top:12px;">';
                                    $html .= $row('Subtotal', $fmt($subtotal), true, true);
                                    if ($isExpress && $expressFeeVal > 0) {
                                        $html .= $row('Biaya Express', $fmt($expressFeeVal), true);
                                    }
                                    $html .= $row('Ongkos Kirim', $fmt($shipping));
                                    $html .= $row('PPn 11%', $fmt($tax));
                                    $html .= $row('Diskon', $fmt($discount));
                                    $html .= $divider;
                                    $html .= $row('Total', $fmt($total), true, true);
                                    $html .= $row('Total Bayar', $fmt($totalPaid));
                                    if ($paymentsHtml) {
                                        $html .= '<div style="margin-top:10px;border-top:1px dashed #e5e7eb;padding-top:10px;">' . $paymentsHtml . '</div>';
                                    }
                                    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">';
                                    $html .= '<span style="color:#6b7280;font-size:14px;">Sisa</span>';
                                    $html .= $remainingLabel;
                                    $html .= '</div>';
                                    
                                    if ($record) {
                                        $html .= '<div style="margin-top:20px; text-align:center;">'
                                            . '<a href="' . route('orders.receipt', $record) . '" target="_blank" style="display:block; text-align:center; padding:10px; background:#f3f4f6; color:#4b5563; border-radius:10px; text-decoration:none; font-weight:700; font-size:12px; border:1px solid #e5e7eb; transition:all 0.2s;">'
                                            . 'Lihat Kuitansi (Tab Baru)'
                                            . '</a>'
                                            . '</div>';
                                    }

                                    $html .= '</div></div>';

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->extraAttributes(['style' => 'position:sticky;']),
                ])
                    ->columnSpan(['lg' => 1, 'default' => 1])
                    ->extraAttributes(['style' => 'align-self:flex-start;position:sticky;top:5rem;']),
            ])
            ->columns(['lg' => 3, 'default' => 1]);
    }

    // Hitung total satu item dari Get $get (saat live form)
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
            $qty = (int) ($get('jumlah_jasa') ?? 0);
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

    // Hitung total satu item dari array $state (sidebar & table)
    protected static function calcItemTotalFromArray(array $state): int
    {
        return (int) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0);
    }

    // Live recalc price + quantity satu item
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

    // Mutate sebelum simpan ke DB: pack JSON + bersihkan virtual fields
    public static function mutateItemData(array $data): array
    {
        // 1. Pack all UI fields into JSON for persistence and back-compatibility
        $data['size_and_request_details'] = [
            'category'          => $data['production_category'] ?? 'produksi',
            'size'              => $data['size'] ?? ($data['ukuran'] ?? null),
            'bahan'             => $data['bahan_baju'] ?? null,
            'gender'            => $data['gender'] ?? null,
            'sleeve_model'      => $data['sleeve_model'] ?? null,
            'pocket_model'      => $data['pocket_model'] ?? null,
            'button_model'      => $data['button_model'] ?? null,
            'measurements'      => [
                'LD'  => $data['LD'] ?? null,
                'PB'  => $data['PB'] ?? null,
                'PL'  => $data['PL'] ?? null,
                'LB'  => $data['LB'] ?? null,
                'LP'  => $data['LP'] ?? null,
                'LPh' => $data['LPh'] ?? null,
            ],
            // For backward compatibility with stock cutting logic (if used)
            'supplier_product'  => $data['supplier_product'] ?? null,
            'varian_ukuran'     => [
                [
                    'ukuran'         => $data['size'] ?? 'No Size',
                    'qty'            => (int) ($data['quantity'] ?? 0),
                    'harga_satuan'   => (int) ($data['price'] ?? 0),
                    'stok_digunakan' => (int) ($data['quantity'] ?? 0),
                ]
            ],
        ];

        // 2. Bersihkan virtual fields agar tidak error SQL "Column not found"
        unset(
            $data['LD'], $data['PB'], $data['PL'], $data['LB'], $data['LP'], $data['LPh'],
            $data['bahan_baju'], $data['gender'], $data['sleeve_model'], 
            $data['pocket_model'], $data['button_model'],
            $data['bahan_usage'], $data['stok_digunakan'], $data['varian_ukuran'],
            $data['request_tambahan'], $data['detail_custom'], $data['supplier_product'],
            $data['np_varian_ukuran'], $data['np_request_tambahan']
        );

        return $data;
    }

    // Unpack JSON data ke virtual field form (Edit mode)
    public static function unmutateItemData(array $data): array
    {
        $details = $data['size_and_request_details'] ?? [];
        if (empty($details)) {
            return $data;
        }

        // 1. Dasar: Kategori & Ukuran
        $cat = $details['category'] ?? ($data['production_category'] ?? 'produksi');
        $data['production_category'] = $cat;
        $data['size'] = $details['size'] ?? ($data['ukuran'] ?? null);

        // 2. Unpack Detail Spec
        $data['bahan_baju'] = $details['bahan'] ?? null;
        $data['gender'] = $details['gender'] ?? null;
        $data['sleeve_model'] = $details['sleeve_model'] ?? null;
        $data['pocket_model'] = $details['pocket_model'] ?? null;
        $data['button_model'] = $details['button_model'] ?? null;
        $data['supplier_product'] = $details['supplier_product'] ?? null;

        // 3. Unpack Measurements (LD, PB, dsb)
        $measurements = $details['measurements'] ?? [];
        $data['LD'] = $measurements['LD'] ?? null;
        $data['PB'] = $measurements['PB'] ?? null;
        $data['PL'] = $measurements['PL'] ?? null;
        $data['LB'] = $measurements['LB'] ?? null;
        $data['LP'] = $measurements['LP'] ?? null;
        $data['LPh'] = $measurements['LPh'] ?? null;

        return $data;
    }

    // Alias for EditOrder page
    public static function unmutateOrderItemData(array $data): array
    {
        return static::unmutateItemData($data);
    }

    // Update subtotal pesanan (sum semua items)
    public static function updateSubtotal(Set $set, Get $get): void
    {
        $items = $get('orderItems') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            // Hitung langsung dari data item (bukan qty├ùprice yang bisa stale/inaccurate)
            $subtotal += static::calcItemTotalFromArray($item);
        }
        $set('subtotal', $subtotal);
        static::updateTotalPrice($set, $get);
    }

    public static function updateTotalPrice(Set $set, Get $get): void
    {
        $subtotal = (int) ($get('subtotal') ?? 0);
        $tax = (int) ($get('tax') ?? 0);
        $shipping = (int) ($get('shipping_cost') ?? 0);
        $discount = (int) ($get('discount') ?? 0);

        $expressFee = 0;
        if ((bool) $get('is_express')) {
            $expressFee = (int) ($get('express_fee') ?? 0);
        }

        $set('total_price', max(0, $subtotal + $tax + $shipping + $expressFee - $discount));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->query(
                Order::query()
                    ->with(['customer', 'orderItems.productionTasks', 'payments'])
            )
            ->columns([

                // KOLOM 1: Pesanan & Pelanggan
                TextColumn::make('order_number')
                    ->label('Pesanan & Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->extraCellAttributes(['style' => 'vertical-align:top;'])
                    ->state(function (Order $record): string {
                        $expressHtml = '';
                        if ($record->is_express) {
                            $expressHtml = '<span style="display:inline-flex; align-items:center; background:#fee2e2; color:#ef4444; border:1px solid #fecaca; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px; text-transform:uppercase;">'
                                . 'EXPRESS</span>';
                        }

                        $orderNum = '<div style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">'
                            . $expressHtml
                            . '<div style="font-weight:600; color:#666666; font-size:13px; letter-spacing:0.04em; text-transform:uppercase;">' . htmlspecialchars($record->order_number) . '</div>'
                            . '</div>';

                        $customer = $record->customer;
                        $name = $customer?->name ?? '-';
                        $phone = $customer?->phone ?? null;

                        $pillClass = 'display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border:1px solid #f3e8ff; border-radius:9999px; background:white; color:#a855f7; font-size:12px; font-weight:500;';

                        $nameBadge = '<div style="' . $pillClass . '">'
                            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                            . htmlspecialchars($name)
                            . '</div>';

                        $phoneLine = $phone
                            ? '<div style="' . $pillClass . '">'
                            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
                            . htmlspecialchars($phone)
                            . '</div>'
                            : '';

                        return '<div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">' . $orderNum . $nameBadge . $phoneLine . '</div>';
                    }),

                // KOLOM 2: Timeline
                TextColumn::make('order_date')
                    ->label('Timeline')
                    ->searchable(false)
                    ->sortable()
                    ->html()
                    ->extraCellAttributes(['style' => 'vertical-align:top;'])
                    ->state(function (Order $record): string {
                        $masukStr = $record->order_date instanceof Carbon ? $record->order_date->format('d M Y') : ($record->order_date ? date('d M Y', strtotime($record->order_date)) : '-');
                        $deadlineStr = $record->deadline instanceof Carbon ? $record->deadline->format('d M Y') : ($record->deadline ? date('d M Y', strtotime($record->deadline)) : '-');

                        $days = $record->deadline
                            ? now()->startOfDay()->diffInDays($record->deadline, false)
                            : null;

                        $sisaBadgeStyle = '';
                        $sisaText = '';
                        $sisaIconColor = '';

                        if ($days === null) {
                            $sisaText = 'Tidak ada';
                            $sisaBadgeStyle = 'color:#9ca3af; border:1px solid #e5e7eb; background:#f9fafb;';
                            $sisaIconColor = '#9ca3af';
                        } elseif ($days < 0) {
                            $sisaText = 'Terlambat ' . abs($days) . ' hari';
                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                            $sisaIconColor = '#e11d48';
                        } elseif ($days === 0) {
                            $sisaText = 'Hari ini';
                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                            $sisaIconColor = '#e11d48';
                        } elseif ($days <= 3) {
                            $sisaText = 'Sisa ' . $days . ' hari';
                            $sisaBadgeStyle = 'color:#e11d48; border:1px solid #fda4af; background:white;';
                            $sisaIconColor = '#e11d48';
                        } elseif ($days <= 7) {
                            $sisaText = 'Sisa ' . $days . ' hari';
                            $sisaBadgeStyle = 'color:#ca8a04; border:1px solid #fbbf24; background:white;';
                            $sisaIconColor = '#ca8a04';
                        } else {
                            $sisaText = 'Sisa ' . $days . ' hari';
                            $sisaBadgeStyle = 'color:#16a34a; border:1px solid #86efac; background:white;';
                            $sisaIconColor = '#16a34a';
                        }

                        $masukHtml = '<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">'
                            . '<span style="font-size:13px; font-weight:500; color:#9ca3af;">' . $masukStr . '</span>'
                            . '<span style="padding:2px 6px; border-radius:4px; background:#f3f4f6; color:#9ca3af; font-size:10px; font-weight:600;">MASUK</span>'
                            . '</div>';

                        $deadlineHtml = '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">'
                            . '<span style="font-size:14px; font-weight:500; color:#4b5563;">' . $deadlineStr . '</span>'
                            . '</div>';

                        $badgeHtml = '<div style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:9999px; font-size:11px; font-weight:500; ' . $sisaBadgeStyle . '">'
                            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="' . $sisaIconColor . '" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>'
                            . $sisaText
                            . '</div>';

                        return '<div style="display:flex; flex-direction:column;">' . $masukHtml . $deadlineHtml . $badgeHtml . '</div>';
                    }),

                // KOLOM 3: Finance
                TextColumn::make('total_price')
                    ->label('Finance')
                    ->sortable()
                    ->html()
                    ->extraCellAttributes(['style' => 'vertical-align:top;'])
                    ->state(function (Order $record): string {
                        $total = (int) $record->total_price;
                        $paid = (int) $record->payments->sum('amount');
                        $sisa = max(0, $total - $paid);
                        $lunas = $sisa === 0;

                        $sisaColor = $lunas ? '#16a34a' : '#7c3aed';
                        $sisaBg = $lunas ? '#f0fdf4' : '#f3e8ff';

                        $sisaHtml = '<div style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:12px; background:' . $sisaBg . '; color:' . $sisaColor . '; font-size:15px; font-weight:700; margin-bottom:8px;">'
                            . ($lunas ? 'LUNAS' : 'Rp ' . number_format($sisa, 0, ',', '.'))
                            . '</div>';

                        $totalHtml = '<div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#6b7280; font-weight:500; margin-bottom:4px;">'
                            . '<span>Total Tagihan</span>'
                            . '<span style="color:#374151; font-weight:600;">Rp ' . number_format($total, 0, ',', '.') . '</span>'
                            . '</div>';

                        $cicilan = $record->payments->count();
                        $paidHtml = $paid > 0
                            ? '<div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#6b7280; font-weight:500;">'
                            . '<span>Sudah Dibayar</span>'
                            . '<span style="color:#374151; font-weight:600;">Rp ' . number_format($paid, 0, ',', '.') . ' (' . $cicilan . 'x)</span>'
                            . '</div>'
                            : '<div style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#f59e0b; font-weight:600;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>Belum ada deposit</div>';

                        return '<div style="display:flex; flex-direction:column; min-width:180px;">' . $sisaHtml . $totalHtml . $paidHtml . '</div>';
                    }),

                // KOLOM 4: Produk & Status Produksi
                TextColumn::make('status')
                    ->label('Produk & Status')
                    ->searchable(false)
                    ->html()
                    ->extraCellAttributes(['style' => 'vertical-align:top;'])
                    ->state(function (Order $record): string {
                        $items = $record->orderItems;

                        if ($items->isEmpty()) {
                            return '<span style="color:#9ca3af;font-size:13px;">-</span>';
                        }

                        $html = '<div style="display:flex; flex-direction:column; gap:16px;">';
                        foreach ($items as $item) {
                            $cat = match ($item->production_category) {
                                'custom' => ['Custom', 'rgba(99,102,241,0.12)', '#6366f1'],
                                'non_produksi' => ['Baju Jadi', 'rgba(245,158,11,0.12)', '#d97706'],
                                'jasa' => ['Jasa', 'rgba(16,185,129,0.12)', '#059669'],
                                default => ['Konveksi', 'rgba(124,58,237,0.10)', '#7c3aed'],
                            };

                            $catBadge = '<div style="display:inline-flex; align-items:center; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; background:' . $cat[1] . '; color:' . $cat[2] . ';">' . $cat[0] . '</div>';

                            // Calculate Base Qty
                            $qty = $item->quantity;
                            if ($item->production_category === 'custom' && !empty($item->size_and_request_details['detail_custom'])) {
                                $qty = count($item->size_and_request_details['detail_custom']);
                            }

                            // Construct Material & Sizing Details
                            $detailsHtml = '';
                            if ($item->production_category !== 'jasa' && $item->production_category !== 'non_produksi') {
                                // Bahan & Warna
                                $matText = '';
                                if (!empty($item->material_details['bahan'])) {
                                    $matText .= $item->material_details['bahan'];
                                }
                                if (!empty($item->material_details['warna'])) {
                                    $matText .= $matText ? ' (' . $item->material_details['warna'] . ')' : $item->material_details['warna'];
                                }

                                if ($matText) {
                                    $detailsHtml .= '<div style="margin-top:4px; margin-bottom:4px; display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#6b7280; font-weight:500; background:#f9fafb; border:1px solid #f3f4f6; padding:2px 8px; border-radius:6px;">'
                                        . '<span>' . htmlspecialchars($matText) . '</span>'
                                        . '</div>';
                                }

                                // Size Breakdown
                                if (!empty($item->sizes)) {
                                    $sizeStrings = [];
                                    $isMultipleSizes = count($item->sizes) > 1;

                                    foreach ($item->sizes as $sizeObj) {
                                        if (is_array($sizeObj) && isset($sizeObj['size']) && isset($sizeObj['quantity'])) {
                                            $sizeStrings[] = htmlspecialchars($sizeObj['size']) . ': <span style="font-weight:700;">' . $sizeObj['quantity'] . '</span>';
                                        }
                                    }

                                    if (count($sizeStrings) > 0) {
                                        $detailsHtml .= '<div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:4px;">';
                                        foreach ($sizeStrings as $szStr) {
                                            $detailsHtml .= '<div style="font-size:11px; color:#4b5563; font-weight:500; background:#f3f4f6; padding:1px 6px; border-radius:4px; letter-spacing:0.02em;">' . $szStr . '</div>';
                                        }
                                        $detailsHtml .= '</div>';
                                    }
                                }
                            }

                            // Progress bar Logic
                            $tasks = $item->productionTasks;
                            $totalTasks = $tasks->count();
                            $doneTasks = $tasks->where('status', 'done')->count();
                            $inProgressTasks = $tasks->where('status', 'in_progress')->count();

                            $pct = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0;

                            $statusText = '<span style="color:#9ca3af;">Belum Diproses</span>';
                            $progressBarHtml = '';

                            if ($totalTasks > 0) {
                                // Find Current Step Status Text
                                if ($pct === 100) {
                                    $statusText = '<span style="color:#10b981; font-weight:700;">Selesai (100%)</span>';
                                } else {
                                    $currentTask = null;
                                    foreach ($tasks as $ti) {
                                        if ($ti->status === 'in_progress') {
                                            $currentTask = $ti;
                                            break;
                                        }
                                    }
                                    if (!$currentTask) {
                                        foreach ($tasks as $ti) {
                                            if ($ti->status === 'pending') {
                                                $currentTask = $ti;
                                                break;
                                            }
                                        }
                                    }

                                    if ($currentTask) {
                                        $statusName = $currentTask->nama_tugas ?? 'Tugas';

                                        if ($currentTask->status === 'in_progress') {
                                            $statusText = '<span style="color:#d97706; font-weight:600;"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#d97706;margin-right:4px;animation:pulse 2s infinite;"></span>' . htmlspecialchars($statusName) . ' (' . $pct . '%)</span>';
                                        } else {
                                            $statusText = '<span style="color:#6b7280; font-weight:500;">Antrian ' . htmlspecialchars($statusName) . ' (' . $pct . '%)</span>';
                                        }
                                    }
                                }

                                // Build Progress Bar UI
                                $barColor = $pct === 100 ? '#10b981' : '#7c3aed';
                                $barBgColor = $pct === 100 ? '#d1fae5' : '#ede9fe';

                                $progressBarHtml = '<div style="margin-top:8px;">'
                                    . '<div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.02em;">'
                                    . '<span>Progress</span>'
                                    . $statusText
                                    . '</div>'
                                    . '<div style="height:6px; background:' . $barBgColor . '; border-radius:9999px; overflow:hidden;">'
                                    . '<div style="height:100%; width:' . $pct . '%; background:' . $barColor . '; border-radius:9999px; transition:width 0.5s ease;"></div>'
                                    . '</div>'
                                    . '</div>';
                            } else {
                                $progressBarHtml = '<div style="margin-top:8px; font-size:11px; color:#9ca3af; font-weight:500;">Tidak ada jadwal</div>';
                            }

                            // Container per Item
                            $html .= '<div style="display:flex; flex-direction:column; background:white; border:1px solid #f3f4f6; border-radius:12px; padding:12px; box-shadow:0 1px 2px rgba(0,0,0,0.02);">'
                                . '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; gap:8px;">'
                                . '<div style="font-size:14px; font-weight:700; color:#111827; line-height:1.4;">'
                                . '<span style="color:#6b7280; margin-right:4px;">' . $qty . 'x</span>' . htmlspecialchars($item->product_name)
                                . '</div>'
                                . $catBadge
                                . '</div>'
                                . $detailsHtml
                                . $progressBarHtml
                                . '</div>';
                        }
                        $html .= '</div>';

                        return $html;
                    }),

                // KOLOM 5: Aksi (Action)
                TextColumn::make('actions')
                    ->label('Aksi')
                    ->alignEnd()
                    ->view('filament.resources.orders.actions')
                    ->extraCellAttributes(['style' => 'vertical-align:top; padding-top:16px;']),
            ])

            ->filters([
                Filter::make('hutang')
                    ->label('Belum Lunas (Piutang)')
                    ->query(fn(Builder $query) => $query->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.order_id = orders.id) < orders.total_price')),

                Filter::make('piutang_macet')
                    ->label('Piutang Macet')
                    ->query(fn(Builder $query) => $query->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.order_id = orders.id) < orders.total_price')->where('deadline', '<', now()->startOfDay())),

                Filter::make('deadline_dekat')
                    ->label('Deadline <= 3 Hari')
                    ->query(fn(Builder $query) => $query->where('deadline', '<=', now()->addDays(3))->where('deadline', '>=', now())),

                SelectFilter::make('tipe_produk')
                    ->label('Tipe Produk')
                    ->options([
                        'produksi' => 'Konveksi',
                        'non_produksi' => 'Baju Jadi',
                        'jasa' => 'Jasa',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value'])
                            return $query;
                        
                        if ($data['value'] === 'produksi') {
                            return $query->whereHas('orderItems', fn($q) => $q->whereIn('production_category', ['produksi', 'custom']));
                        }
                        
                        return $query->whereHas('orderItems', fn($q) => $q->where('production_category', $data['value']));
                    }),
            ])

            ->actions([
                Action::make('create_return')
                    ->label('Retur Pesanan')
                    ->icon('heroicon-o-arrow-path')
                    ->form(\App\Filament\Resources\OrderReturns\Schemas\OrderReturnForm::getComponents(true))
                    ->action(function (Order $record, array $data): void {
                        $record->returns()->create($data);
                        Notification::make()
                            ->title('Retur Berhasil Dicatat')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Catat Retur Pesanan')
                    ->modalSubmitActionLabel('Simpan Retur')
                    ->extraAttributes(['class' => 'hidden']),
                DeleteAction::make()
                    ->visible(fn() => auth()->user()->role === 'owner')
                    ->extraAttributes(['class' => 'hidden']),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->role === 'owner'),
                ]),
            ])
            ->defaultSort('is_express', 'desc')
            ->modifyQueryUsing(fn($query) => $query->orderBy('is_express', 'desc')->orderBy('order_date', 'desc'));
    }



    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager::class,
            \App\Filament\Resources\Orders\RelationManagers\ReturnsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function isStokVisibleForVariant(Get $get): bool
    {
        $productId = $get('../../supplier_product');
        $size = $get('ukuran');

        if (!$productId || !$size) {
            return false;
        }

        $variant = \App\Models\ProductVariant::where('product_id', $productId)
            ->where(function ($q) use ($size) {
                $q->whereRaw('LOWER(size) = ?', [strtolower($size)])
                    ->orWhere('size', '=', $size);
            })->first();

        return $variant && $variant->stock > 0;
    }
}
