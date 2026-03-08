<?php

namespace App\Filament\Resources\OrderReturns;

use App\Filament\Resources\OrderReturns\Pages\CreateOrderReturn;
use App\Filament\Resources\OrderReturns\Pages\EditOrderReturn;
use App\Filament\Resources\OrderReturns\Pages\ListOrderReturns;
use App\Filament\Resources\OrderReturns\Pages\ViewOrderReturn;
use App\Filament\Resources\OrderReturns\Schemas\OrderReturnForm;
use App\Filament\Resources\OrderReturns\Schemas\OrderReturnInfolist;
use App\Filament\Resources\OrderReturns\Tables\OrderReturnsTable;
use App\Models\OrderReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Retur Pesanan';
    protected static ?string $pluralLabel = 'Retur Pesanan';
    protected static ?string $modelLabel = 'Retur Pesanan';

    protected static ?string $recordTitleAttribute = 'items_description';

    public static function form(Schema $schema): Schema
    {
        return OrderReturnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderReturnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderReturnsTable::configure($table);
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
            'index' => ListOrderReturns::route('/'),
            'create' => CreateOrderReturn::route('/create'),
            'view' => ViewOrderReturn::route('/{record}'),
            'edit' => EditOrderReturn::route('/{record}/edit'),
        ];
    }
}
