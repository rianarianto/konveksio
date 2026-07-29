<?php

namespace App\Filament\Resources\OrderReturns\Tables;

use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\OrderReturn;

class OrderReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['order', 'orderItem']))
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('orderItem.product_name')
                    ->label('Produk')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('return_date')
                    ->label('Tanggal Retur')
                    ->date('d M Y')
                    ->description(function (OrderReturn $record) {
                        if ($record->expected_pickup_date) {
                            return '🎯 Target: ' . $record->expected_pickup_date->format('d M Y');
                        }
                        return null;
                    })
                    ->sortable(),

                TextColumn::make('responsibility_type')
                    ->label('Penanggung Jawab')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'customer_paid' => '💳 Kesalahan Customer',
                        default => '🛡️ Garansi Toko',
                    })
                    ->color(fn ($state) => match ($state) {
                        'customer_paid' => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('action_type')
                    ->label('Tindakan')
                    ->badge()
                    ->formatStateUsing(fn ($state, OrderReturn $record) => match ($state) {
                        'remake' => '🏭 Buat Baru',
                        default => '🛠️ Perbaikan (' . ucfirst($record->target_stage ?? 'Jahit') . ')',
                    })
                    ->color('info'),

                TextColumn::make('size_breakdown')
                    ->label('Ukuran / Pcs')
                    ->formatStateUsing(function ($state, OrderReturn $record) {
                        if (!empty($state) && is_array($state)) {
                            $pairs = [];
                            foreach ($state as $k => $v) {
                                $displayKey = $k === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $k;
                                $pairs[] = "{$displayKey}: {$v}";
                            }
                            return implode(', ', $pairs);
                        }
                        return $record->quantity . ' pcs';
                    }),

                TextColumn::make('additional_fee')
                    ->label('Biaya Extra')
                    ->money('IDR')
                    ->sortable(),

                ImageColumn::make('photo_path')
                    ->label('Foto Bukti')
                    ->circular(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Pending',
                        'diproses' => '🔨 Diproses',
                        'selesai' => '✅ Selesai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'diproses' => 'info',
                        'selesai' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->before(function (OrderReturn $record) {
                        $itemId = $record->order_item_id;

                        // 1. Hapus task revisi retur
                        if ($itemId) {
                            \App\Models\ProductionTask::withoutGlobalScopes()
                                ->where('order_item_id', $itemId)
                                ->where('is_revision', true)
                                ->delete();

                            // 2. Jika tidak ada retur aktif lainnya, kembalikan status WorkOrder ke COMPLETED
                            $hasOtherReturns = OrderReturn::where('order_item_id', $itemId)
                                ->where('id', '!=', $record->id)
                                ->whereIn('status', ['pending', 'diproses'])
                                ->exists();

                            if (!$hasOtherReturns) {
                                $wo = \App\Models\WorkOrder::where('order_item_id', $itemId)->first();
                                if ($wo) {
                                    $wo->update([
                                        'status' => \App\Models\WorkOrder::STATUS_COMPLETED,
                                        'current_review_stage' => null,
                                    ]);
                                }
                            }
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
