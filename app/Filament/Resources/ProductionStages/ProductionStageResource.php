<?php

namespace App\Filament\Resources\ProductionStages;

use App\Filament\Resources\ProductionStages\Pages\ManageProductionStages;
use App\Models\ProductionStage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionStageResource extends Resource
{
    protected static ?string $model = ProductionStage::class;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Data Master';
    }

    public static function getModelLabel(): string
    {
        return 'Tahapan Produksi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tahapan Produksi';
    }

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Tahapan')
                    ->required()
                    ->maxLength(255),
                TextInput::make('base_wage')
                    ->label('Upah Satuan Dasar (Rp)')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->prefix('Rp'),
                Toggle::make('for_produksi_custom')
                    ->label('Kategori Produksi & Custom')
                    ->default(true)
                    ->helperText('Tahapan ini akan muncul pada produk komplit/custom.'),
                Toggle::make('for_non_produksi')
                    ->label('Kategori Non-Produksi')
                    ->default(true)
                    ->helperText('Tahapan ini akan muncul pada produk jadi dari supplier (misal: hanya Sablon & QC).'),
                Toggle::make('for_jasa')
                    ->label('Kategori Jasa')
                    ->default(true)
                    ->helperText('Tahapan ini akan muncul pada pengerjaan jasa murni (misal: hanya Sablon & QC).'),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tahapan')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('base_wage')
                    ->label('Upah Dasar (Rp)')
                    ->numeric()
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                IconColumn::make('for_produksi_custom')
                    ->label('Produksi / Custom')
                    ->boolean(),
                IconColumn::make('for_non_produksi')
                    ->label('Non Produksi')
                    ->boolean(),
                IconColumn::make('for_jasa')
                    ->label('Jasa')
                    ->boolean(),
            ])
            ->defaultSort('order_sequence')
            ->reorderable('order_sequence')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductionStages::route('/'),
        ];
    }
}
