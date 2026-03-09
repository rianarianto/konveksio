<?php

namespace App\Filament\Resources\PrintTypes;

use App\Filament\Resources\PrintTypes\Pages\ManagePrintTypes;
use App\Models\PrintType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class PrintTypeResource extends Resource
{
    protected static ?string $model = PrintType::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Data Sablon / Bordir';
    protected static ?string $modelLabel = 'Sablon / Bordir';
    protected static ?string $pluralModelLabel = 'Data Sablon / Bordir';

    protected static string|\UnitEnum|null $navigationGroup = 'INVENTORI & MASTER';
    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'jenis' => '🎨 Jenis / Teknik',
                        'lokasi' => '📍 Lokasi / Titik',
                    ])
                    ->required()
                    ->default('jenis'),
                TextInput::make('name')
                    ->label('Nama')
                    ->placeholder('Contoh: Sablon, Bordir, Dada Kiri, Punggung')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'jenis' => '🎨 Jenis / Teknik',
                        'lokasi' => '📍 Lokasi / Titik',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'jenis' => '🎨 Jenis / Teknik',
                        'lokasi' => '📍 Lokasi / Titik',
                    ]),
            ])
            ->defaultSort('category')
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

    public static function getPages(): array
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_START,
            fn(): HtmlString => new HtmlString("
                <style>
                    .fi-tabs, .fi-sc-tabs, .fi-tabs-list {
                        width: 100% !important;
                        justify-content: flex-start !important;
                    }
                    .fi-tabs > * {
                        justify-content: flex-start !important;
                    }
                </style>
            "),
            scopes: ManagePrintTypes::class,
        );

        return [
            'index' => ManagePrintTypes::route('/'),
        ];
    }
}
