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
                        
                        $additionTag = $item->is_addition ? ' ➕ [Batch Penambahan]' : ' 📦 [Batch Utama]';
                        
                        if (!empty($record->size_breakdown) && is_array($record->size_breakdown) && count($record->size_breakdown) > 1) {
                            return "{$item->product_name} (Beberapa Ukuran){$additionTag}";
                        }

                        $size = $item->size ?: 'All Size';
                        $gender = $item->size_and_request_details['gender'] ?? null;
                        $genderLabel = $gender ? ($gender === 'L' ? 'Laki-laki' : 'Perempuan') : null;
                        
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
                    ->formatStateUsing(fn ($state) => '🔧 ' . implode(', ', array_map('ucfirst', explode(',', (string)$state)))),

                TextColumn::make('quantity')
                    ->label('Qty Retur')
                    ->getStateUsing(fn (OrderReturn $record): string => $record->quantity . ' pcs')
                    ->description(function (OrderReturn $record) {
                        if (!empty($record->size_breakdown) && is_array($record->size_breakdown)) {
                            $parts = [];
                            foreach ($record->size_breakdown as $sz => $q) {
                                $parts[] = "{$sz}: {$q} pcs";
                            }
                            return implode(', ', $parts);
                        }
                        return null;
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

                        $retur = new OrderReturn();
                        if (isset($data['target_stages'])) {
                            $retur->target_stages = $data['target_stages'];
                            unset($data['target_stages']);
                        }
                        if (isset($data['multi_size_items'])) {
                            $retur->multi_size_items = $data['multi_size_items'];
                            unset($data['multi_size_items']);
                        }
                        if (isset($data['selected_product_name'])) {
                            unset($data['selected_product_name']);
                        }
                        if (isset($data['selection_mode'])) {
                            unset($data['selection_mode']);
                        }

                        $retur->fill($data);
                        $retur->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Retur Pesanan Berhasil Dicatat')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    \Filament\Actions\Action::make('view_detail')
                        ->label('Detail Retur')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading('🎯 Detail Lengkap Retur & Garansi Pesanan')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup Modal')
                        ->form(function (OrderReturn $record): array {
                            $breakdownText = '-';
                            if (!empty($record->size_breakdown) && is_array($record->size_breakdown)) {
                                $lines = [];
                                foreach ($record->size_breakdown as $sz => $q) {
                                    $lines[] = "• {$sz} ➔ {$q} pcs";
                                }
                                $breakdownText = implode("\n", $lines);
                            }

                            $garansiText = $record->responsibility_type === 'customer_paid' 
                                ? '💳 Berbayar Customer' . ($record->additional_fee > 0 ? ' (Biaya Tambahan: Rp ' . number_format($record->additional_fee, 0, ',', '.') . ')' : '')
                                : '🛡️ Garansi Toko / Cacat Produksi (Gratis)';

                            $stagesText = implode(', ', array_map('ucfirst', explode(',', (string)$record->target_stage)));

                            return [
                                \Filament\Schemas\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('product_info')
                                            ->label('📦 Nama Produk & Batch')
                                            ->content($record->orderItem?->product_name . ($record->orderItem?->is_addition ? ' (Batch Penambahan Baru)' : ' (Batch Utama)')),

                                        \Filament\Forms\Components\Placeholder::make('garansi_info')
                                            ->label('🛡️ Garansi & Tanggung Jawab')
                                            ->content($garansiText),

                                        \Filament\Forms\Components\Placeholder::make('stages_info')
                                            ->label('🔧 Divisi Tujuan Perbaikan')
                                            ->content($stagesText),

                                        \Filament\Forms\Components\Placeholder::make('total_qty_info')
                                            ->label('📊 Total Qty & Rincian Ukuran Cacat')
                                            ->content(new \Illuminate\Support\HtmlString("<strong>Total Retur: {$record->quantity} pcs</strong><br><pre style=\"margin-top:4px;font-family:sans-serif;background:#f8fafc;padding:8px;border-radius:6px;\">{$breakdownText}</pre>")),
                                    ]),

                                \Filament\Forms\Components\Textarea::make('full_reason')
                                    ->label('📝 Detail Cacat & Catatan Perbaikan')
                                    ->default($record->items_description ?: $record->reason)
                                    ->rows(5)
                                    ->columnSpanFull()
                                    ->disabled(),
                            ];
                        }),

                    \Filament\Actions\Action::make('edit_return')
                        ->label('Edit Retur')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->visible(function (OrderReturn $record): bool {
                            $user = auth()->user();
                            if (!$user) return false;
                            if ($user->role === 'owner') return true;

                            if ($user->role === 'admin') {
                                $itemId = $record->order_item_id;
                                $hasTasksStarted = \App\Models\ProductionTask::withoutGlobalScopes()
                                    ->where('order_item_id', $itemId)
                                    ->where('is_revision', true)
                                    ->where('created_at', '>=', $record->created_at?->subSeconds(10) ?? now())
                                    ->whereIn('status', ['in_progress', 'done'])
                                    ->exists();

                                return !$hasTasksStarted;
                            }

                            return false;
                        })
                        ->fillForm(fn (OrderReturn $record): array => [
                            'shop_id' => $record->shop_id,
                            'order_id' => $record->order_id,
                            'selected_product_name' => $record->orderItem?->product_name,
                            'order_item_id' => $record->order_item_id,
                            'quantity' => $record->quantity,
                            'action_type' => $record->action_type,
                            'target_stages' => explode(',', (string)$record->target_stage),
                            'responsibility_type' => $record->responsibility_type,
                            'additional_fee' => $record->additional_fee,
                            'return_date' => $record->return_date,
                            'expected_pickup_date' => $record->expected_pickup_date,
                            'items_description' => $record->items_description ?: $record->reason,
                        ])
                        ->form(OrderReturnForm::getComponents(true))
                        ->action(function (OrderReturn $record, array $data): void {
                            if (isset($data['target_stages'])) {
                                $record->target_stages = $data['target_stages'];
                                unset($data['target_stages']);
                            }
                            if (isset($data['multi_size_items'])) {
                                $record->multi_size_items = $data['multi_size_items'];
                                unset($data['multi_size_items']);
                            }
                            if (isset($data['selected_product_name'])) {
                                unset($data['selected_product_name']);
                            }
                            if (isset($data['selection_mode'])) {
                                unset($data['selection_mode']);
                            }

                            $record->fill($data);
                            $record->save();

                            \Filament\Notifications\Notification::make()
                                ->title('Retur Pesanan Berhasil Diperbarui')
                                ->success()
                                ->send();
                        }),

                    \Filament\Actions\Action::make('delete_return')
                        ->label('Hapus Retur')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(function (OrderReturn $record): bool {
                            $user = auth()->user();
                            if (!$user) return false;
                            if ($user->role === 'owner') return true;

                            if ($user->role === 'admin') {
                                $itemId = $record->order_item_id;
                                $hasTasksStarted = \App\Models\ProductionTask::withoutGlobalScopes()
                                    ->where('order_item_id', $itemId)
                                    ->where('is_revision', true)
                                    ->where('created_at', '>=', $record->created_at?->subSeconds(10) ?? now())
                                    ->whereIn('status', ['in_progress', 'done'])
                                    ->exists();

                                return !$hasTasksStarted;
                            }

                            return false;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Retur Pesanan?')
                        ->modalDescription('Apakah Anda yakin ingin menghapus transaksi retur ini? Work Order retur dan tugas perbaikan akan dibersihkan.')
                        ->action(function (OrderReturn $record): void {
                            $record->delete();

                            \Filament\Notifications\Notification::make()
                                ->title('Retur Berhasil Dihapus')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
