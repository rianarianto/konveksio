<?php

namespace App\Filament\Resources\WorkerPayrolls;

use App\Models\ProductionTask;
use App\Models\Worker;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class WorkerPayrollResource extends Resource
{
    protected static ?string $model = Worker::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Rekap Upah';
    protected static ?string $modelLabel = 'Rekap Upah';
    protected static ?string $slug = 'worker-payroll';
    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';
    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('productionTasks', fn($q) => $q->where('status', 'done')->where('is_paid', false));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('pesanan_count')
                    ->label('Jml Pesanan')
                    ->state(function (Worker $record): int {
                        return $record->productionTasks()
                            ->where('production_tasks.status', 'done')
                            ->where('production_tasks.is_paid', false)
                            ->join('order_items', 'production_tasks.order_item_id', '=', 'order_items.id')
                            ->distinct('order_items.order_id')
                            ->count('order_items.order_id');
                    }),

                TextColumn::make('items_count')
                    ->label('Jml Item')
                    ->state(function (Worker $record): int {
                        return $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false)
                            ->distinct('order_item_id')
                            ->count('order_item_id');
                    }),

                TextColumn::make('total_pcs')
                    ->label('Total Pcs')
                    ->state(function (Worker $record): int {
                        return (int) $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false)
                            ->sum('quantity');
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_wage')
                    ->label('Total Nominal Upah')
                    ->state(function (Worker $record): int {
                        return (int) $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false)
                            ->selectRaw('SUM(wage_amount * quantity) as total')
                            ->value('total') ?? 0;
                    })
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('current_cash_advance')
                    ->label('Sisa Kasbon')
                    ->money('IDR')
                    ->color(fn($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->actions([
                Action::make('pay_all')
                    ->label('Bayar Borongan')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Bayar Semua Upah Selesai')
                    ->modalDescription(function (Worker $record) {
                        $total = (int) $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false)
                            ->selectRaw('SUM(wage_amount * quantity) as total')
                            ->value('total') ?? 0;
                        return "Apakah Anda yakin ingin membayar seluruh upah selesai untuk {$record->name} sebesar Rp " . number_format($total, 0, ',', '.') . "?";
                    })
                    ->action(function (Worker $record) {
                        $unpaidTasks = $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false)
                            ->get();

                        if ($unpaidTasks->isEmpty())
                            return;

                        $totalAmount = 0;
                        foreach ($unpaidTasks as $task) {
                            $totalAmount += ($task->wage_amount * $task->quantity);
                            $task->update(['is_paid' => true]);
                        }

                        Expense::create([
                            'shop_id' => $record->shop_id,
                            'keperluan' => "Upah Borongan: {$record->name} (" . now()->format('d/m/Y') . ")",
                            'amount' => $totalAmount,
                            'expense_date' => now(),
                            'note' => 'Gaji / Upah',
                            'recorded_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Pembayaran Berhasil')
                            ->body("Upah sebesar Rp " . number_format($totalAmount, 0, ',', '.') . " telah dibayar dan dicatat.")
                            ->send();
                    }),

                Action::make('detail')
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Worker $record) => \App\Filament\Resources\Workers\WorkerResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('name');
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
