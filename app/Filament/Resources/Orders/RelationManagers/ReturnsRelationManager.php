<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Filament\Resources\OrderReturns\Schemas\OrderReturnForm;
use App\Models\OrderReturn;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'returns';
    
    protected static ?string $title = 'Retur & Garansi Pesanan';

    public function form(Schema $schema): Schema
    {
        return $schema->components(OrderReturnForm::getComponents(true));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_date')
                    ->label('Tanggal Retur')
                    ->date('d M Y')
                    ->description(function (OrderReturn $record) {
                        if ($record->expected_pickup_date) {
                            return '🎯 Target Selesai: ' . $record->expected_pickup_date->format('d M Y');
                        }
                        return null;
                    })
                    ->sortable(),

                TextColumn::make('orderItem.product_name')
                    ->label('Varian / Ukuran Item')
                    ->formatStateUsing(function (OrderReturn $record) {
                        $item = $record->orderItem;
                        if (!$item) return '-';
                        
                        $size = $item->size ?: 'All Size';
                        $gender = $item->size_and_request_details['gender'] ?? null;
                        $genderLabel = $gender ? ($gender === 'L' ? 'Laki-laki' : 'Perempuan') : null;
                        $additionTag = $item->is_addition ? ' ➕ [Tambahan]' : '';
                        
                        return "{$item->product_name} [Size {$size}" . ($genderLabel ? " - {$genderLabel}" : '') . "]{$additionTag}";
                    })
                    ->description(fn (OrderReturn $record) => $record->orderItem?->recipient_name ? '👤 Penerima: ' . $record->orderItem->recipient_name : null),

                TextColumn::make('responsibility_type')
                    ->label('Tipe Garansi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'customer_paid' => '💳 Berbayar (Kesalahan Customer)',
                        default => '🛡️ Garansi Toko (Gratis)',
                    })
                    ->color(fn ($state) => match ($state) {
                        'customer_paid' => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('items_description')
                    ->label('Catatan Perbaikan / Defect')
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('target_stage')
                    ->label('Tujuan Perbaikan')
                    ->formatStateUsing(fn ($state) => '🔧 ' . ucfirst($state ?: 'Jahit')),

                TextColumn::make('quantity')
                    ->label('Qty Retur')
                    ->formatStateUsing(function ($state, OrderReturn $record) {
                        if (!empty($record->size_breakdown) && is_array($record->size_breakdown)) {
                            $parts = [];
                            foreach ($record->size_breakdown as $sz => $q) {
                                $parts[] = "{$sz}: {$q} pcs";
                            }
                            return implode(', ', $parts);
                        }
                        return $state . ' pcs';
                    })
                    ->weight('bold'),

                ImageColumn::make('photo_path')
                    ->label('Foto Bukti')
                    ->circular(),

                TextColumn::make('status')
                    ->label('Status Antrian')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Pending (Di Antrian)',
                        'diproses' => '🔨 Diproses Tukang',
                        'selesai' => '✅ Selesai Retur',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'diproses' => 'primary',
                        'selesai' => 'success',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('create_return')
                    ->label('Tambah Retur Pesanan')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('primary')
                    ->fillForm(fn (): array => [
                        'order_id' => $this->getOwnerRecord()?->id,
                        'shop_id' => $this->getOwnerRecord()?->shop_id ?? \Filament\Facades\Filament::getTenant()?->id,
                    ])
                    ->form(OrderReturnForm::getComponents(true))
                    ->action(function (array $data): void {
                        $data['shop_id'] = $this->getOwnerRecord()?->shop_id ?? \Filament\Facades\Filament::getTenant()?->id;
                        $order = $this->getOwnerRecord();
                        if ($order) {
                            $data['order_id'] = $order->id;
                        }
                        OrderReturn::create($data);

                        \Filament\Notifications\Notification::make()
                            ->title('Retur Pesanan Berhasil Dicatat')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                \Filament\Actions\Action::make('delete_return')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Retur Pesanan?')
                    ->modalDescription('Apakah Anda yakin ingin menghapus transaksi retur ini? Task perbaikan tukang akan dibersihkan.')
                    ->action(function (OrderReturn $record): void {
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

                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Retur Berhasil Dihapus')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
