<?php

namespace App\Filament\Resources\WorkerPayrolls;

use App\Models\ProductionTask;
use App\Models\Worker;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class WorkerPayrollResource extends Resource
{
    protected static ?string $model = ProductionTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Rekap Upah';
    protected static ?string $modelLabel = 'Rekap Upah';
    protected static ?string $slug = 'worker-payroll';
    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';
    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function scopeEloquentQueryToTenant(\Illuminate\Database\Eloquent\Builder $query, ?\Illuminate\Database\Eloquent\Model $tenant): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('shop_id', $tenant?->getKey());
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->with(['assignedTo', 'orderItem.order']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assignedTo.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('stage_name')
                    ->label('Tahap')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('orderItem.product_name')
                    ->label('Produk')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('orderItem.order.order_number')
                    ->label('No. Pesanan')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->suffix(' pcs')
                    ->sortable(),

                TextColumn::make('wage_amount')
                    ->label('Upah/pcs')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('total_upah')
                    ->label('Total Upah')
                    ->state(fn(ProductionTask $record): int => $record->wage_amount * $record->quantity)
                    ->money('IDR')
                    ->sortable(false)
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('completed_at')
                    ->label('Selesai Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('kasbon_info')
                    ->label('Sisa Kasbon')
                    ->state(function (ProductionTask $record): string {
                        $worker = $record->assignedTo;
                        if (!$worker || $worker->current_cash_advance <= 0)
                            return '-';
                        return 'Rp ' . number_format($worker->current_cash_advance, 0, ',', '.');
                    })
                    ->color(fn(ProductionTask $record): string => (
                        $record->assignedTo?->current_cash_advance > 0 ? 'danger' : 'gray'
                    )),
            ])
            ->filters([
                SelectFilter::make('assigned_to')
                    ->label('Karyawan')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Status Bayar')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah Dibayar')
                    ->falseLabel('Belum Dibayar')
                    ->default(false),

                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal'),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn($q, $v) => $q->whereDate('completed_at', '>=', $v))
                            ->when($data['sampai'], fn($q, $v) => $q->whereDate('completed_at', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari'])
                            $indicators[] = 'Dari: ' . $data['dari'];
                        if ($data['sampai'])
                            $indicators[] = 'Sampai: ' . $data['sampai'];
                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Action::make('pay')
                    ->label('Bayar Upah')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->hidden(fn($record) => $record->is_paid)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pembayaran Upah')
                    ->modalDescription('Apakah Anda yakin ingin membayar upah untuk tugas ini? Tindakan ini akan mencatat pengeluaran otomatis.')
                    ->action(function (ProductionTask $record) {
                        $record->update(['is_paid' => true]);

                        \App\Models\Expense::create([
                            'shop_id' => $record->shop_id,
                            'keperluan' => "Upah Borongan: {$record->assignedTo->name} (#{$record->orderItem->order->order_number})",
                            'amount' => $record->wage_amount * $record->quantity,
                            'expense_date' => now(),
                            'note' => 'Gaji / Upah',
                            'recorded_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Pembayaran Berhasil')
                            ->body('Upah telah dibayar dan dicatat di Pengeluaran.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('pay_selected')
                    ->label('Bayar Massal')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $records->each(function (ProductionTask $record) {
                            if (!$record->is_paid) {
                                $record->update(['is_paid' => true]);

                                \App\Models\Expense::create([
                                    'shop_id' => $record->shop_id,
                                    'keperluan' => "Upah Borongan: {$record->assignedTo->name} (#{$record->orderItem->order->order_number})",
                                    'amount' => $record->wage_amount * $record->quantity,
                                    'expense_date' => now(),
                                    'note' => 'Gaji / Upah',
                                    'recorded_by' => auth()->id(),
                                ]);
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Pembayaran Massal Berhasil')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->heading('Rekap Upah Karyawan')
            ->description('Daftar pekerjaan selesai beserta upah. Filter berdasarkan periode & karyawan.')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkerPayrolls::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
