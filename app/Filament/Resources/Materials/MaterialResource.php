<?php

namespace App\Filament\Resources\Materials;

use App\Models\Material;
use App\Models\Supplier;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
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

    protected static ?string $navigationLabel = 'Stok Bahan';
    protected static ?string $modelLabel = 'Bahan Baku';
    protected static ?string $pluralModelLabel = 'Stok Bahan';

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

                ColorPicker::make('color_code')
                    ->label('Warna'),

                TextInput::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric()
                    ->placeholder('0')
                    ->suffix(fn($get) => $get('unit') ?? 'Kg'),

                TextInput::make('min_stock')
                    ->label('Stok Minimal (Peringatan)')
                    ->numeric()
                    ->placeholder('0')
                    ->helperText('Sistem akan memberi peringatan jika stok di bawah angka ini.')
                    ->suffix(fn($get) => $get('unit') ?? 'Kg'),

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
                ColorColumn::make('color_code')
                    ->label('Warna'),
                TextColumn::make('current_stock')
                    ->label('Stok')
                    ->formatStateUsing(fn($record) => number_format($record->current_stock, 0, ',', '.') . ' ' . $record->unit)
                    ->sortable()
                    ->color(fn($record) => $record->current_stock <= $record->min_stock ? 'danger' : null),
                TextColumn::make('min_stock')
                    ->label('Min')
                    ->formatStateUsing(fn($record) => number_format($record->min_stock, 0, ',', '.') . ' ' . $record->unit)
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
            'index' => ListMaterials::route('/'),
            'create' => CreateMaterial::route('/create'),
            'edit' => EditMaterial::route('/{record}/edit'),
        ];
    }
}
