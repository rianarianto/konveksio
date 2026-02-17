<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Customer;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                        ])
                        ->columns(2),

                    // Section 3: Data Produk Pesanan
                    Section::make('Data Produk Pesanan')
                        ->schema([
                            Repeater::make('orderItems')
                                ->relationship()
                                ->schema([
                                    TextInput::make('product_name')
                                        ->label('Produksi')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->label('Jumlah')
                                        ->required()
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->live()
                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                        ->columnSpan(1),

                                    TextInput::make('price')
                                        ->label('Harga')
                                        ->required()
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->live()
                                        ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                        ->columnSpan(1),
                                ])
                                ->columns(4)
                                ->defaultItems(0)
                                ->addActionLabel('+ Tambah Produk')
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                ->deleteAction(
                                    fn($action) => $action->after(fn(Set $set, Get $get) => static::updateSubtotal($set, $get))
                                ),
                        ]),

                    // Section 4: Pembayaran
                    Section::make('Pembayaran')
                        ->schema([
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

                            TextInput::make('total_price')
                                ->label('Total')
                                ->numeric()
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated()
                                ->default(0),

                            TextInput::make('down_payment')
                                ->label('DP')
                                ->numeric()
                                ->prefix('Rp')
                                ->default(0)
                                ->live(),

                            Placeholder::make('remaining_payment')
                                ->label('Sisa Tagihan')
                                ->content(function (Get $get): string {
                                    $total = $get('total_price') ?? 0;
                                    $dp = $get('down_payment') ?? 0;
                                    $remaining = $total - $dp;
                                    return 'Rp ' . number_format($remaining, 0, ',', '.');
                                }),
                        ])
                        ->columns(3),
                ])
                    ->columnSpan(2),

                // RIGHT SIDE (Ringkasan Pesanan Sidebar) - 1 column
                Group::make([
                    Section::make('Ringkasan Pesanan')
                        ->schema([
                            Placeholder::make('summary_items')
                                ->label('Produk')
                                ->content(function (Get $get): string {
                                    $items = $get('orderItems') ?? [];
                                    if (empty($items)) {
                                        return 'Belum ada produk';
                                    }
                                    
                                    $html = '<div class="space-y-2">';
                                    foreach ($items as $item) {
                                        if (!empty($item['product_name'])) {
                                            $qty = $item['quantity'] ?? 0;
                                            $html .= '<div class="flex items-center gap-2">';
                                            $html .= '<span class="inline-block bg-purple-600 text-white text-xs px-2 py-1 rounded">Produksi</span>';
                                            $html .= '<span class="text-sm">' . $qty . 'x ' . htmlspecialchars($item['product_name']) . '</span>';
                                            $html .= '</div>';
                                        }
                                    }
                                    $html .= '</div>';
                                    return $html;
                                })
                                ->columnSpan('full'),

                            Placeholder::make('summary_subtotal')
                                ->label('Subtotal')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('subtotal') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_shipping')
                                ->label('Ongkos Kirim')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('shipping_cost') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_tax')
                                ->label('PPN 11%')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('tax') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_discount')
                                ->label('Diskon')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('discount') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_total')
                                ->label('Total')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('total_price') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_dp')
                                ->label('DP')
                                ->content(fn(Get $get): string => 'Rp ' . number_format($get('down_payment') ?? 0, 0, ',', '.')),

                            Placeholder::make('summary_total_paid')
                                ->label('Total Pelunasan')
                                ->content(function (Get $get): string {
                                    $total = $get('total_price') ?? 0;
                                    $dp = $get('down_payment') ?? 0;
                                    $paid = $total - $dp;
                                    return 'Rp ' . number_format($paid, 0, ',', '.');
                                }),

                            Placeholder::make('summary_remaining')
                                ->label('Sisa')
                                ->content(function (Get $get): string {
                                    $total = $get('total_price') ?? 0;
                                    $dp = $get('down_payment') ?? 0;
                                    $remaining = $total - $dp;
                                    
                                    if ($remaining <= 0) {
                                        return '<span class="inline-block bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full font-semibold">Lunas</span>';
                                    }
                                    
                                    return 'Rp ' . number_format($remaining, 0, ',', '.');
                                }),
                        ])
                        ->columns(2),
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
            'index' => ManageOrders::route('/'),
        ];
    }
}
