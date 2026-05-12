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
use Filament\Schemas\Components\Grid;
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

    protected static ?string $navigationLabel = 'Katalog Baju';
    protected static ?string $modelLabel = 'Baju';
    protected static ?string $pluralModelLabel = 'Katalog Baju';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';
    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin', 'designer']);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
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

                Repeater::make('color_matrix')
                    ->label('Matrix Stok Baju (Warna x Ukuran)')
                    ->schema([
                        Grid::make(9)->schema([
                            TextInput::make('color_name')
                                ->label('Nama Warna')
                                ->placeholder('Hitam, Merah...')
                                ->required()
                                ->columnSpan(2),
                            
                            ColorPicker::make('color_code')
                                ->label('Warna')
                                ->columnSpan(1),
                            
                            TextInput::make('qty_xs')->label('XS')->numeric()->placeholder('0')->columnSpan(1),
                            TextInput::make('qty_s')->label('S')->numeric()->placeholder('0')->columnSpan(1),
                            TextInput::make('qty_m')->label('M')->numeric()->placeholder('0')->columnSpan(1),
                            TextInput::make('qty_l')->label('L')->numeric()->placeholder('0')->columnSpan(1),
                            TextInput::make('qty_xl')->label('XL')->numeric()->placeholder('0')->columnSpan(1),
                            TextInput::make('qty_xxl')->label('2XL')->numeric()->placeholder('0')->columnSpan(1),
                        ]),
                    ])
                    ->defaultItems(1)
                    ->addActionLabel('+ Tambah Warna Baru')
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Repeater $component, ?\App\Models\Product $record) {
                        if (!$record) return;
                        
                        $variants = $record->variants;
                        $matrix = [];
                        
                        $groupedByColor = $variants->groupBy('color_name');
                        
                        foreach ($groupedByColor as $color => $items) {
                            $row = [
                                'color_name' => $color,
                                'color_code' => $items->first()->color_code ?? null,
                            ];
                            
                            foreach ($items as $item) {
                                $row['qty_' . strtolower($item->size)] = $item->stock;
                            }
                            
                            $matrix[] = $row;
                        }
                        
                        $component->state($matrix);
                    })
                    ->saveRelationshipsUsing(function (\App\Models\Product $record, array $state) {
                        $record->variants()->delete();
                        
                        foreach ($state as $row) {
                            $color = $row['color_name'] ?? 'Default';
                            $code = $row['color_code'] ?? null;
                            
                            $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl'];
                            
                            foreach ($sizes as $size) {
                                $qty = (int) ($row['qty_' . $size] ?? 0);
                                
                                if ($qty > 0) {
                                    $record->variants()->create([
                                        'color_name' => $color,
                                        'color_code' => $code,
                                        'size' => strtoupper($size),
                                        'selling_price' => 0,
                                        'purchase_price' => 0,
                                        'stock' => $qty,
                                    ]);
                                }
                            }
                        }
                    }),
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
                TextColumn::make('variants.color_name')
                    ->label('Warna Tersedia')
                    ->badge()
                    ->separator(', ')
                    ->distinct(),
                ColorColumn::make('variants.color_code')
                    ->label('Swatch')
                    ->circular(),
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
