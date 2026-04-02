<?php

namespace App\Filament\Resources\StoreSizes;

use App\Filament\Resources\StoreSizes\Pages\CreateStoreSize;
use App\Filament\Resources\StoreSizes\Pages\EditStoreSize;
use App\Filament\Resources\StoreSizes\Pages\ListStoreSizes;
use App\Filament\Resources\StoreSizes\Schemas\StoreSizeForm;
use App\Filament\Resources\StoreSizes\Tables\StoreSizesTable;
use App\Models\StoreSize;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreSizeResource extends Resource
{
    protected static ?string $model = StoreSize::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';

    protected static ?string $modelLabel = 'Ukuran Toko';
    
    protected static ?string $pluralModelLabel = 'Daftar Ukuran Toko';

    protected static ?int $navigationSort = 6;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return StoreSizeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreSizesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreSizes::route('/'),
            'create' => CreateStoreSize::route('/create'),
            'edit' => EditStoreSize::route('/{record}/edit'),
        ];
    }
}
