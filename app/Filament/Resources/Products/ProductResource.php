<?php

namespace App\Filament\Resources\Products;

use App\Models\Product;
use App\Models\Supplier;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Stok Barang Jadi';
    protected static ?string $modelLabel = 'Barang Jadi';
    protected static ?string $pluralModelLabel = 'Stok Barang Jadi';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';
    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Barang')
                    ->placeholder('Contoh: Kaos Polos Premium')
                    ->required()
                    ->maxLength(255),

                TextInput::make('type')
                    ->label('Jenis Barang')
                    ->placeholder('Contoh: Kaos, Polo, Hoodie')
                    ->maxLength(255),

                ColorPicker::make('color_code')
                    ->label('Warna'),

                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nama Supplier')
                            ->required(),
                        TextInput::make('phone')
                            ->label('No. HP')
                            ->tel(),
                        Select::make('type')
                            ->label('Kategori')
                            ->options([
                                'Kain' => '🧵 Kain',
                                'Aksesoris' => '🪡 Aksesoris',
                                'Baju Jadi' => '👕 Baju Jadi',
                                'Lainnya' => '📦 Lainnya',
                            ]),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return Supplier::create([
                            'shop_id' => Filament::getTenant()->id,
                            'name' => $data['name'],
                            'phone' => $data['phone'] ?? null,
                            'type' => $data['type'] ?? null,
                        ])->getKey();
                    })
                    ->nullable(),

                Repeater::make('variants')
                    ->label('Varian Ukuran')
                    ->relationship()
                    ->schema([
                        Select::make('size')
                            ->label('Ukuran')
                            ->options([
                                'XS' => 'XS',
                                'S' => 'S',
                                'M' => 'M',
                                'L' => 'L',
                                'XL' => 'XL',
                                'XXL' => 'XXL',
                                'XXXL' => 'XXXL',
                                'All Size' => 'All Size',
                            ])
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('purchase_price')
                            ->label('Harga Beli (Modal)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        TextInput::make('selling_price')
                            ->label('Harga Jual')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        TextInput::make('stock')
                            ->label('Stok')
                            ->numeric()
                            ->placeholder('0')
                            ->required(),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->addActionLabel('+ Tambah Varian Ukuran')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->searchable(),
                ColorColumn::make('color_code')
                    ->label('Warna'),
                TextColumn::make('variants_count')
                    ->label('Varian')
                    ->counts('variants')
                    ->sortable(),
                TextColumn::make('variants_sum_stock')
                    ->label('Total Stok')
                    ->sum('variants', 'stock')
                    ->suffix(' pcs')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
