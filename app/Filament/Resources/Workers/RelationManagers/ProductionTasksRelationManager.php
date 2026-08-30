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
                    ->label('Qty & Ukuran')
                    ->alignLeft()
                    ->html()
                    ->getStateUsing(function (ProductionTask $record): string {
                        $qtyBadge = '<span style="display:inline-block; font-weight:800; font-size:13px; color:#1d4ed8; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:2px 8px;">' . $record->quantity . ' pcs</span>';
                        
                        $sq = $record->size_quantities;
                        $sizeDetails = [];

                        if (!empty($sq) && is_array($sq)) {
                            $cleanSizes = array_filter($sq, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
                            $genders = $sq['_genders'] ?? [];
                            $customRecipients = $sq['_custom_recipients'] ?? [];

                            foreach ($cleanSizes as $sz => $cnt) {
                                if ($cnt <= 0) continue;
                                $displaySz = $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz;
                                
                                if (!empty($genders[$sz])) {
                                    $gStrs = [];
                                    foreach ($genders[$sz] as $gLabel => $gQty) {
                                        $gStrs[] = ($gLabel === 'P' ? 'P' : 'L') . ':' . $gQty;
                                    }
                                    $sizeDetails[] = "<strong>{$displaySz}</strong> (" . implode(', ', $gStrs) . ')';
                                } else {
                                    $sizeDetails[] = "<strong>{$displaySz}</strong>: {$cnt}";
                                }
                            }

                            if (!empty($customRecipients)) {
                                $sizeDetails[] = '<span style="color:#d97706; font-weight:600;">📏 Ukur Badan: ' . implode(', ', array_slice($customRecipients, 0, 3)) . (count($customRecipients) > 3 ? '...' : '') . '</span>';
                            }
                        }

                        if (!empty($sizeDetails)) {
                            return '<div style="text-align:left;">' . $qtyBadge . '<div style="font-size:12px; color:#64748b; margin-top:4px; line-height:1.4; text-align:left;">' . implode(' • ', $sizeDetails) . '</div></div>';
                        }

                        if (!empty($record->description)) {
                            $firstLine = explode("\n", $record->description)[0];
                            return '<div style="text-align:left;">' . $qtyBadge . '<div style="font-size:12px; color:#64748b; margin-top:4px; text-align:left;">' . e($firstLine) . '</div></div>';
                        }

                        return '<div style="text-align:left;">' . $qtyBadge . '</div>';
                    }),

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
                    ->label('Selesai')
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
