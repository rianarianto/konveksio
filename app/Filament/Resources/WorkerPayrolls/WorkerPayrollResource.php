<?php

namespace App\Filament\Resources\WorkerPayrolls;

use App\Models\ProductionTask;
use App\Models\Worker;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\WorkerPayrolls\Pages\ManageWorkerPayrolls;
use Illuminate\Support\HtmlString; // Added missing import

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
            ->with(['productionTasks' => function ($query) {
                $query->where('status', 'done')->where('is_paid', false)->with('orderItem');
            }])
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
            return $record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->pluck('orderItem.order_id')
                ->unique()
                ->count();
        }),

            TextColumn::make('items_count')
            ->label('Jml Item')
            ->state(function (Worker $record): int {
            return $record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->pluck('order_item_id')
                ->unique()
                ->count();
        }),

            TextColumn::make('total_pcs')
            ->label('Total Pcs')
            ->state(function (Worker $record): int {
            return (int)$record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->sum('quantity');
        })
            ->badge()
            ->color('info'),

            TextColumn::make('total_wage')
            ->label('Total Nominal Upah')
            ->state(function (Worker $record): int {
            return (int)$record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->sum('wage_amount');
        })
            ->money('IDR')
            ->weight('bold')
            ->color('success'),

            TextColumn::make('current_cash_advance')
            ->label('Sisa Kasbon (-) ')
            ->money('IDR')
            ->color(fn($state): string => $state > 0 ? 'danger' : 'gray'),

            TextColumn::make('net_wage')
            ->label('Upah Bersih')
            ->state(function (Worker $record): int {
            $totalWage = (int)$record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->sum('wage_amount');

            return max(0, $totalWage - $record->current_cash_advance);
        })
            ->money('IDR')
            ->weight('bold')
            ->color('primary'),
        ])
            ->actions([
            Action::make('pay_all')
            ->label('Bayar Upah')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Bayar Semua Upah Selesai')
            ->modalDescription(function (Worker $record) {
            $totalWage = (int)$record->productionTasks()
                ->where('status', 'done')
                ->where('is_paid', false)
                ->selectRaw('SUM(wage_amount) as total')
                ->value('total') ?? 0;

            $kasbon = (int)$record->current_cash_advance;
            $netWage = max(0, $totalWage - $kasbon);

            $desc = "Total Upah: Rp " . number_format($totalWage, 0, ',', '.') . "\n";
            if ($kasbon > 0) {
                $desc .= "Potongan Kasbon: Rp " . number_format($kasbon, 0, ',', '.') . "\n";
                $desc .= "Jumlah Dibayarkan: Rp " . number_format($netWage, 0, ',', '.') . "\n\n";

                if ($kasbon > $totalWage) {
                    $desc .= "⚠️ Upah tidak mencukupi untuk melunasi kasbon. Sisa kasbon akan berkurang Rp " . number_format($totalWage, 0, ',', '.') . ".";
                }
                else {
                    $desc .= "✅ Kasbon akan dianggap LUNAS.";
                }
            }
            else {
                $desc .= "Apakah Anda yakin ingin membayar seluruh upah selesai?";
            }

            return new \Illuminate\Support\HtmlString(nl2br(e($desc)));
        })
            ->action(function (Worker $record) {
            $unpaidTasks = $record->productionTasks()
                ->where('status', 'done')
                ->where('is_paid', false)
                ->get();

            if ($unpaidTasks->isEmpty())
                return;

            $totalWage = 0;
            foreach ($unpaidTasks as $task) {
                $totalWage += $task->wage_amount;
                $task->update(['is_paid' => true]);
            }

            $kasbonBefore = (int)$record->current_cash_advance;
            $deduction = min($totalWage, $kasbonBefore);
            $netToPay = $totalWage - $deduction;

            $record->decrement('current_cash_advance', $deduction);

            if ($netToPay > 0) {
                Expense::create([
                        'shop_id' => $record->shop_id,
                        'keperluan' => "Upah Borongan (Net): {$record->name} (" . now()->format('d/m/Y') . ")",
                        'amount' => $netToPay,
                        'expense_date' => now(),
                        'note' => 'Gaji / Upah',
                        'recorded_by' => auth()->id(),
                    ]);
            }

            if ($deduction > 0) {
                $record->cashAdvances()->create([
                        'shop_id' => $record->shop_id,
                        'amount' => $deduction,
                        'type' => 'repayment',
                        'description' => "Potong dari upah borongan (" . now()->format('d/m/Y') . ")",
                        'recorded_by' => auth()->id(),
                    ]);
            }

            Notification::make()
                ->success()
                ->title('Pembayaran Selesai')
                ->body("Upah Rp " . number_format($totalWage, 0, ',', '.') . " diproses. Potong Kasbon: Rp " . number_format($deduction, 0, ',', '.') . ". Dibayar Tunai: Rp " . number_format($netToPay, 0, ',', '.') . ".")
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

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkerPayrolls::route('/'),
        ];
    }
}
