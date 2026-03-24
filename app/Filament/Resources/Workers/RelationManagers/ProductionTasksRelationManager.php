<?php

namespace App\Filament\Resources\Workers\RelationManagers;

use App\Models\ProductionTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;

class ProductionTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'productionTasks';

    protected static ?string $title = 'Riwayat Pekerjaan & Upah';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stage_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['orderItem.order']))
            ->columns([
                TextColumn::make('orderItem.order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('orderItem.product_name')
                    ->label('Produk')
                    ->description(fn (ProductionTask $record): string => $record->orderItem->production_category ?? '-')
                    ->searchable(),

                TextColumn::make('stage_name')
                    ->label('Tahap'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->suffix(' pcs')
                    ->badge()
                    ->color('info'),

                TextColumn::make('wage_amount')
                    ->label('Total Upah')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Antrian',
                        'in_progress' => '🔨 Dikerjakan',
                        'done' => '✅ Selesai',
                        default => $state,
                    }),

                TextColumn::make('completed_at')
                    ->label('Selesai Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => '⏳ Antrian',
                        'in_progress' => '🔨 Dikerjakan',
                        'done' => '✅ Selesai',
                    ]),
                
                SelectFilter::make('stage_name')
                    ->label('Tahap Pekerjaan')
                    ->options(\App\Models\ProductionTask::distinct()->pluck('stage_name', 'stage_name')->toArray()),

                Filter::make('completed_at')
                    ->form([
                        DatePicker::make('completed_from')->label('Selesai Dari'),
                        DatePicker::make('completed_until')->label('Selesai Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '<=', $date),
                            );
                    })
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
