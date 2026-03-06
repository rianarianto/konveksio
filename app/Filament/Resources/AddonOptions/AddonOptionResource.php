<?php

namespace App\Filament\Resources\AddonOptions;

use App\Models\AddonOption;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\AddonOptions\Pages\ListAddonOptions;
use App\Filament\Resources\AddonOptions\Pages\CreateAddonOptionPage;
use App\Filament\Resources\AddonOptions\Pages\EditAddonOption;

class AddonOptionResource extends Resource
{
    protected static ?string $model = AddonOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Request Tambahan';
    protected static ?string $modelLabel = 'Request Tambahan';
    protected static ?string $pluralModelLabel = 'Request Tambahan';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';
    protected static ?int $navigationSort = 4;

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
                    ->label('Nama Tambahan')
                    ->placeholder('Contoh: Saku Semi Klewang, Bordir Nama, Label Jahit')
                    ->required()
                    ->maxLength(255),

                TextInput::make('default_price')
                    ->label('Harga Default (per unit)')
                    ->helperText('Bisa diubah saat input pesanan')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Nonaktifkan jika tidak ingin muncul di form pesanan'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tambahan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_price')
                    ->label('Harga Default')
                    ->money('IDR')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddonOptions::route('/'),
            'create' => CreateAddonOptionPage::route('/create'),
            'edit' => EditAddonOption::route('/{record}/edit'),
        ];
    }
}
