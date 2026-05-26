<?php

namespace App\Filament\Resources\Products;

use App\Models\Product;
use App\Models\Supplier;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
            ->columns(1)
            ->components([
                Section::make('Informasi Dasar Baju')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Baju')
                            ->placeholder('Contoh: Kaos Polos Premium')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('type')
                            ->label('Jenis Baju')
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
                                        'Baju Jadi' => '👕 Non-Produksi',
                                        'Lainnya' => '📦 Lainnya',
                                    ]),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return \App\Models\Supplier::create([
                                    'shop_id' => Filament::getTenant()->id,
                                    'name' => $data['name'],
                                    'phone' => $data['phone'] ?? null,
                                    'type' => $data['type'] ?? null,
                                ])->getKey();
                            })
                            ->nullable(),
                    ]),

                Section::make('Varian Warna & Stok')
                    ->schema([
                        Repeater::make('color_matrix')
                            ->label('Matrix Stok Baju (Warna x Ukuran)')
                            ->schema(function () {
                                $storeSizes = \App\Models\StoreSize::where('shop_id', Filament::getTenant()->id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get();

                                $fields = [
                                    TextInput::make('color_name')
                                        ->label('Nama Warna')
                                        ->placeholder('Hitam, Merah...')
                                        ->required()
                                        ->rules([
                                            fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                                                $rows = $get('../../color_matrix') ?? [];
                                                
                                                // Filament v5 pakai UUID sebagai key repeater, bukan integer
                                                preg_match('/color_matrix\.([^.]+)\.color_name/', $attribute, $matches);
                                                $currentKey = $matches[1] ?? null;

                                                foreach ($rows as $key => $row) {
                                                    if ((string) $key === (string) $currentKey) {
                                                        continue;
                                                    }

                                                    if (isset($row['color_name']) && strtolower(trim($row['color_name'])) === strtolower(trim($value))) {
                                                        $fail('Nama warna ini sudah digunakan pada barang ini.');
                                                        return;
                                                    }
                                                }
                                            }
                                        ]),
                                    
                                    TextInput::make('color_code')
                                        ->label('Kode Warna')
                                        ->placeholder('misal: BLK-01')
                                        ->required()
                                        ->rules([
                                            fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                                                $rows = $get('../../color_matrix') ?? [];
                                                
                                                // Filament v5 pakai UUID sebagai key repeater, bukan integer
                                                preg_match('/color_matrix\.([^.]+)\.color_code/', $attribute, $matches);
                                                $currentKey = $matches[1] ?? null;

                                                foreach ($rows as $key => $row) {
                                                    if ((string) $key === (string) $currentKey) {
                                                        continue;
                                                    }

                                                    if (isset($row['color_code']) && strtolower(trim($row['color_code'])) === strtolower(trim($value))) {
                                                        $fail('Kode warna ini sudah digunakan pada barang ini.');
                                                        return;
                                                    }
                                                }
                                            }
                                        ]),
                                ];

                                foreach ($storeSizes as $size) {
                                    $fields[] = TextInput::make('qty_' . strtolower($size->name))
                                        ->label($size->name)
                                        ->numeric()
                                        ->integer()
                                        ->placeholder('0');
                                }

                                return $fields;
                            })
                            ->table(function () {
                                $storeSizes = \App\Models\StoreSize::where('shop_id', Filament::getTenant()->id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get();

                                $columns = [
                                    TableColumn::make('Nama Warna')->markAsRequired(),
                                    TableColumn::make('Kode Warna')->markAsRequired(),
                                ];

                                foreach ($storeSizes as $size) {
                                    $columns[] = TableColumn::make($size->name);
                                }

                                return $columns;
                            })
                            ->compact()
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
                        
                        $storeSizes = \App\Models\StoreSize::where('shop_id', Filament::getTenant()->id)
                            ->where('is_active', true)
                            ->pluck('name')
                            ->toArray();

                        foreach ($state as $row) {
                            $color = $row['color_name'] ?? 'Default';
                            $code = $row['color_code'] ?? null;
                            
                            foreach ($storeSizes as $sizeName) {
                                $qty = (int) ($row['qty_' . strtolower($sizeName)] ?? 0);
                                
                                if ($qty > 0) {
                                    $record->variants()->create([
                                        'color_name' => $color,
                                        'color_code' => $code,
                                        'size' => $sizeName,
                                        'selling_price' => 0,
                                        'purchase_price' => 0,
                                        'stock' => $qty,
                                    ]);
                                }
                            }
                        }
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Baju')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->searchable(),
                 TextColumn::make('variants_summary')
                    ->label('Warna & Stok')
                    ->getStateUsing(function ($record) {
                        $variants = $record->variants;
                        $grouped = $variants->groupBy('color_name');

                        $html = '<div class="flex flex-wrap gap-1.5 py-1">';
                        foreach ($grouped as $colorName => $items) {
                            $code = $items->first()->color_code ?? '';
                            $totalStock = $items->sum('stock');
                            $isHex = is_string($code) && str_starts_with($code, '#');
                            $dot = $isHex ? "<span class='w-2 h-2 rounded-full border border-black/10 shrink-0' style='background-color:{$code}'></span>" : "";

                            $label = $colorName . ($code ? " ({$code})" : "");

                            $html .= "<div class='inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-50 border border-gray-200 shadow-sm whitespace-nowrap'>";
                            $html .= $dot;
                            $html .= "<span class='text-[10px] font-bold text-gray-700'>{$label}</span>";
                            $html .= "<span class='text-[10px] font-black text-primary-600 border-l border-gray-200 pl-1.5'>{$totalStock} pcs</span>";
                            $html .= "</div>";
                        }
                        $html .= '</div>';
                        return $html;
                    })
                    ->html()
                    ->searchable(['variants.color_name']),
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
