<?php

namespace App\Filament\Resources\Materials;

use App\Models\Material;
use App\Models\Supplier;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
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
use App\Filament\Resources\Materials\Pages\ListMaterials;
use App\Filament\Resources\Materials\Pages\CreateMaterial;
use App\Filament\Resources\Materials\Pages\EditMaterial;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $navigationLabel = 'Katalog Bahan';
    protected static ?string $modelLabel = 'Bahan';
    protected static ?string $pluralModelLabel = 'Katalog Bahan';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';
    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar Bahan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Bahan')
                            ->placeholder('Contoh: Cotton Combed 30s')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('type')
                            ->label('Jenis Bahan')
                            ->placeholder('Contoh: Kain, Benang, Kancing')
                            ->maxLength(255),

                        Select::make('unit')
                            ->label('Satuan')
                            ->options([
                                'Kg' => 'Kg',
                                'Meter' => 'Meter',
                                'Roll' => 'Roll',
                                'Yard' => 'Yard',
                                'Pcs' => 'Pcs',
                                'Lusin' => 'Lusin',
                            ])
                            ->default('Kg')
                            ->required(),

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
                        Repeater::make('variants')
                            ->label('Daftar Warna')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('color_name')
                                            ->label('Nama Warna')
                                            ->placeholder('misal: Navy Blue')
                                            ->required(),
                                        ColorPicker::make('color_code')
                                            ->label('Visual'),
                                        TextInput::make('current_stock')
                                            ->label('Stok Saat Ini')
                                            ->numeric()
                                            ->default(0)
                                            ->required(),
                                        TextInput::make('min_stock')
                                            ->label('Stok Min.')
                                            ->numeric()
                                            ->default(0)
                                            ->required(),
                                    ]),
                            ])
                            ->collapsible()
                            ->defaultItems(1)
                            ->itemLabel(fn(array $state): ?string => $state['color_name'] ?? null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Bahan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->searchable(),

                TextColumn::make('variants_summary')
                    ->label('Warna & Stok')
                    ->getStateUsing(function ($record) {
                        $variants = collect($record->variants);
                        $count = $variants->count();
                        $limit = 6;
                        $display = $variants->take($limit);

                        $html = '<div class="flex flex-wrap gap-1.5 py-1">';
                        foreach ($display as $v) {
                            $dot = $v->color_code ? "<span class='w-2 h-2 rounded-full border border-black/10 shrink-0' style='background-color:{$v->color_code}'></span>" : "";
                            $stock = number_format($v->current_stock, 0, ',', '.');
                            $html .= "<div class='inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-50 border border-gray-200 shadow-sm whitespace-nowrap'>";
                            $html .= $dot;
                            $html .= "<span class='text-[10px] font-bold text-gray-700'>{$v->color_name}</span>";
                            $unit = match ($record->unit) {
                                'Meter' => 'M',
                                'Kg' => 'Kg',
                                'Lusin' => 'Lsn',
                                default => strtolower($record->unit),
                            };
                            $html .= "<span class='text-[10px] font-black text-primary-600 border-l border-gray-200 pl-1.5'>{$stock} {$unit}</span>";
                            $html .= "</div>";
                        }

                        if ($count > $limit) {
                            $remaining = $count - $limit;
                            $html .= "<span class='text-[10px] font-bold text-gray-400 py-0.5 px-1 whitespace-nowrap'>+{$remaining} lainnya</span>";
                        }

                        $html .= '</div>';
                        return $html;
                    })
                    ->html()
                    ->searchable(['variants.color_name']),
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
            'index' => ListMaterials::route('/'),
            'create' => CreateMaterial::route('/create'),
            'edit' => EditMaterial::route('/{record}/edit'),
        ];
    }
}
