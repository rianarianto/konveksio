<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Models\Customer;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
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
                                    if (empty($address)) {
                                        return '';
                                    }
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

                    // Section 3: Data Produk Pesanan
                    Section::make('Data Produk Pesanan')
                        ->schema([
                            Repeater::make('orderItems')
                                ->relationship()
                                ->schema([
                                    Group::make([
                                        TextInput::make('product_name')
                                            ->label('Produksi')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->required()
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->live()
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                            ->columnSpan(1),

                                        TextInput::make('price')
                                            ->label('Harga Satuan')
                                            ->required()
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->live()
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                            ->columnSpan(2),

                                        Placeholder::make('total_item')
                                            ->label('Total')
                                            ->content(function (Get $get): string {
                                                $qty = (int) $get('quantity');
                                                $price = (int) $get('price');
                                                return 'Rp ' . number_format($qty * $price, 0, ',', '.');
                                            })
                                            ->columnSpan(2),
                                    ])->columns(9)
                                ])
                                ->columns(1)
                                ->defaultItems(0)
                                ->addActionLabel('+ Tambah Produk')
                                ->addAction(fn($action) => $action->color('primary')->extraAttributes(['style' => 'color: #7F00FF; border-color: #7F00FF; background-color: #F3E8FF;'])) // Light purple bg, purple text
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                ->deleteAction(
                                    fn($action) => $action->after(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                ),
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

                            Group::make([
                                TextInput::make('total_price')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->extraInputAttributes(['style' => 'font-size: 1.25rem; font-weight: bold; color: #7e22ce;']) // Purple bold
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpanFull(),
                            ]),

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
                                            '<span class="font-xl font-bold ' . $color . '" style="font-size:20px;">' .
                                            'Rp ' . number_format($remaining, 0, ',', '.') .
                                            '</span>'
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

                // RIGHT SIDE (Ringkasan Pesanan Sidebar) - 1 column
                Group::make([
                    Section::make('Ringkasan Pesanan')
                        ->schema([
                            Placeholder::make('summary_full')
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

                                    // Product cards
                                    $itemsHtml = '';
                                    $hasItems = false;
                                    foreach ($items as $item) {
                                        if (!empty($item['product_name'])) {
                                            $hasItems = true;
                                            $qty = $item['quantity'] ?? 0;
                                            $name = htmlspecialchars($item['product_name']);
                                            $itemsHtml .= '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e9d5ff;border-radius:10px;background:#faf5ff;margin-bottom:8px;">';
                                            $itemsHtml .= '<span style="background:#f3e8ff;color:#7c3aed;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap;">Produksi</span>';
                                            $itemsHtml .= '<span style="font-size:14px;font-weight:500;color:#1f2937;">' . $qty . 'x ' . $name . '</span>';
                                            $itemsHtml .= '</div>';
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

                                    $html = '<div style="font-family:inherit;">';
                                    $html .= $itemsHtml;
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
                                    $html .= '</div>';
                                    $html .= '</div></div>';

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(1),
                ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    protected static function updateSubtotal(Set $set, Get $get): void
    {
        $items = $get('orderItems') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (int) ($item['price'] ?? 0);
            $subtotal += $quantity * $price;
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

        $total = $subtotal + $tax + $shipping - $discount;
        $set('total_price', max(0, $total));
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
            ->filters([
                //
            ])
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
