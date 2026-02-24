<?php

namespace App\Filament\Resources\ControlProduksis;

use App\Filament\Resources\ControlProduksis\Pages\ManageControlProduksis;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionStage;
use App\Models\User;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\ViewColumn;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class ControlProduksiResource extends Resource
{
    protected static ?string $model = \App\Models\OrderItem::class;

    protected static ?string $slug = 'control-produksi';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getModelLabel(): string
    {
        return 'Tugas Produksi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Control Produksi';
    }

    protected static bool $isScopedToTenant = true;

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Override and do nothing.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('design_status', 'approved')
            ->with(['order.customer', 'productionTasks'])
            ->latest('id');
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The form schema is now handled via Page Actions in ManageControlProduksis
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('product_name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('production_category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'produksi' => 'primary',
                        'custom' => 'warning',
                        'non_produksi' => 'gray',
                        'jasa' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('progress_status')
                    ->label('Status Produksi')
                    ->badge()
                    ->state(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->count() === 0)
                            return 'Belum Diproses';
                        if ($tasks->where('status', '!=', 'done')->count() === 0)
                            return 'Selesai';
                        return 'Sedang Berjalan';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diproses' => 'gray',
                        'Sedang Berjalan' => 'primary',
                        'Selesai' => 'success',
                        default => 'gray',
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
            )
            ->filters([
                // Filters handled by Tabs in Manage page
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageControlProduksis::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
