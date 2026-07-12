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
use App\Models\WorkerPayroll;
use App\Filament\Resources\WorkerPayrolls\Pages\ManageWorkerPayrolls;
use Illuminate\Support\HtmlString;

class WorkerPayrollResource extends Resource
{
    protected static ?string $model = Worker::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Bayar Gaji & Upah';
    protected static ?string $modelLabel = 'Bayar Gaji & Upah';
    protected static ?string $pluralModelLabel = 'Bayar Gaji & Upah';
    protected static ?string $slug = 'worker-payroll';
    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';
    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['productionTasks' => function ($query) {
                $query->where('status', 'done')->where('is_paid', false)->with('orderItem');
            }]);
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
            ->visible(fn($livewire) => ($livewire->activeTab ?? 'borongan') === 'borongan')
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
            ->visible(fn($livewire) => ($livewire->activeTab ?? 'borongan') === 'borongan')
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
            ->visible(fn($livewire) => ($livewire->activeTab ?? 'borongan') === 'borongan')
            ->state(function (Worker $record): int {
            return (int)$record->productionTasks
                ->where('status', 'done')
                ->where('is_paid', false)
                ->sum('quantity');
        })
            ->badge()
            ->color('info'),

            TextColumn::make('total_wage')
            ->label('Total Nominal')
            ->state(function (Worker $record, $livewire): int {
                if (($livewire->activeTab ?? 'borongan') === 'bulanan') {
                    return (int) $record->base_salary;
                }
                return (int) $record->productionTasks
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
            ->state(function (Worker $record, $livewire): int {
                $totalWage = 0;
                if (($livewire->activeTab ?? 'borongan') === 'bulanan') {
                    $totalWage = (int) $record->base_salary;
                } else {
                    $totalWage = (int) $record->productionTasks
                        ->where('status', 'done')
                        ->where('is_paid', false)
                        ->sum('wage_amount');
                }
                return max(0, $totalWage - $record->current_cash_advance);
            })
            ->money('IDR')
            ->weight('bold')
            ->color('primary'),
        ])
            ->actions([
            Action::make('pay_all')
            ->label(fn($livewire) => ($livewire->activeTab ?? 'borongan') === 'bulanan' ? 'Bayar Gaji' : 'Bayar Upah')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn() => auth()->user()->role === 'owner')
            ->modalHeading(fn($livewire) => ($livewire->activeTab ?? 'borongan') === 'bulanan' ? 'Bayar Gaji Bulanan' : 'Bayar Upah Borongan')
            ->form(function ($livewire) {
                $isMonthly = (($livewire->activeTab ?? 'borongan') === 'bulanan');

                if ($isMonthly) {
                    return [
                        \Filament\Forms\Components\Placeholder::make('summary')
                            ->label('Ringkasan Pembayaran')
                            ->content(function(Worker $record) {
                                $total = (int) $record->base_salary;
                                $kasbon = (int) $record->current_cash_advance;
                                $net = max(0, $total - $kasbon);
                                return new \Illuminate\Support\HtmlString("
                                    <div style='background:#f3f4f6; padding:12px; border-radius:8px; border:1px solid #e5e7eb;'>
                                        <table style='width:100%; border-collapse:collapse; font-size:13px;'>
                                            <tr><td>Gaji Pokok Kotor:</td><td style='text-align:right; font-weight:bold;'>Rp " . number_format($total, 0, ',', '.') . "</td></tr>
                                            <tr style='color:#ef4444;'><td>Potongan Kasbon:</td><td style='text-align:right;'>- Rp " . number_format(min($total, $kasbon), 0, ',', '.') . "</td></tr>
                                            <tr style='border-top:1px solid #d1d5db; font-size:14px; font-weight:bold; color:#16a34a;'><td style='padding-top:6px;'>Gaji Bersih Diterima:</td><td style='text-align:right; padding-top:6px;'>Rp " . number_format($net, 0, ',', '.') . "</td></tr>
                                        </table>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                        \Filament\Forms\Components\Select::make('salary_month')
                            ->label('Gaji Bulan')
                            ->options(function() {
                                $months = [];
                                for ($i = 0; $i < 6; $i++) {
                                    $date = now()->subMonths($i);
                                    $key = $date->format('Y-m');
                                    $label = $date->translatedFormat('F Y');
                                    $months[$key] = $label;
                                }
                                return $months;
                            })
                            ->default(now()->format('Y-m'))
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('period_start')
                            ->label('Periode Kerja Dari')
                            ->native(false)
                            ->default(now()->startOfMonth())
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('period_end')
                            ->label('Periode Kerja Sampai')
                            ->native(false)
                            ->default(now()->endOfMonth())
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->placeholder('Masukkan keterangan tambahan jika ada...'),
                    ];
                } else {
                    return [
                        \Filament\Forms\Components\Placeholder::make('summary')
                            ->label('Ringkasan Pembayaran (Total Saat Ini)')
                            ->content(function(Worker $record) {
                                $total = (int) $record->productionTasks()
                                    ->where('status', 'done')
                                    ->where('is_paid', false)
                                    ->sum('wage_amount');
                                $kasbon = (int) $record->current_cash_advance;
                                $net = max(0, $total - $kasbon);
                                return new \Illuminate\Support\HtmlString("
                                    <div style='background:#f3f4f6; padding:12px; border-radius:8px; border:1px solid #e5e7eb;'>
                                        <table style='width:100%; border-collapse:collapse; font-size:13px;'>
                                            <tr><td>Estimasi Upah Kotor:</td><td style='text-align:right; font-weight:bold;'>Rp " . number_format($total, 0, ',', '.') . "</td></tr>
                                            <tr style='color:#ef4444;'><td>Potongan Kasbon:</td><td style='text-align:right;'>- Rp " . number_format(min($total, $kasbon), 0, ',', '.') . "</td></tr>
                                            <tr style='border-top:1px solid #d1d5db; font-size:14px; font-weight:bold; color:#16a34a;'><td style='padding-top:6px;'>Estimasi Upah Bersih:</td><td style='text-align:right; padding-top:6px;'>Rp " . number_format($net, 0, ',', '.') . "</td></tr>
                                        </table>
                                        <div style='font-size:11px; color:#6b7280; margin-top:8px;'>* Nominal bersih final akan disesuaikan otomatis jika Anda membatasi tanggal pekerjaan di bawah.</div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                        \Filament\Forms\Components\DatePicker::make('period_start')
                            ->label('Pekerjaan Dari Tanggal')
                            ->native(false)
                            ->placeholder('Semua pekerjaan sebelumnya')
                            ->helperText('Kosongkan jika ingin membayar seluruh pekerjaan yang belum dibayar.'),
                        \Filament\Forms\Components\DatePicker::make('period_end')
                            ->label('Pekerjaan Sampai Tanggal')
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->helperText('Hanya pekerjaan yang diselesaikan sampai tanggal ini yang akan dibayar.'),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->placeholder('Masukkan keterangan tambahan jika ada...'),
                    ];
                }
            })
            ->action(function (Worker $record, array $data, $livewire) {
                DB::transaction(function () use ($record, $data, $livewire) {
                    $isMonthly = (($livewire->activeTab ?? 'borongan') === 'bulanan');

                    if ($isMonthly) {
                        $totalWage = (int) $record->base_salary;
                    } else {
                        $startDate = $data['period_start'] ?? null;
                        $endDate = $data['period_end'] ?? now();

                        $query = $record->productionTasks()
                            ->where('status', 'done')
                            ->where('is_paid', false);

                        if ($startDate) {
                            $query->whereDate('completed_at', '>=', $startDate);
                        }
                        $query->whereDate('completed_at', '<=', $endDate);

                        $unpaidTasks = $query->get();

                        if ($unpaidTasks->isEmpty()) {
                            Notification::make()
                                ->danger()
                                ->title('Pembayaran Gagal')
                                ->body("Tidak ditemukan pekerjaan borongan selesai pada rentang tanggal tersebut.")
                                ->send();
                            return;
                        }

                        $totalWage = 0;
                        foreach ($unpaidTasks as $task) {
                            $totalWage += $task->wage_amount;
                        }
                    }

                    $kasbonBefore = (int)$record->current_cash_advance;
                    $deduction = min($totalWage, $kasbonBefore);
                    $netToPay = $totalWage - $deduction;

                    // 1. Create Employee Payroll Record
                    $payroll = WorkerPayroll::create([
                        'shop_id' => $record->shop_id,
                        'worker_id' => $record->id,
                        'total_wage' => $totalWage,
                        'kasbon_deduction' => $deduction,
                        'net_amount' => $netToPay,
                        'payment_date' => now(),
                        'recorded_by' => auth()->id(),
                        'note' => $data['note'] ?? ($isMonthly ? 'Gaji Bulanan' : 'Upah Borongan'),
                        'period_start' => $data['period_start'] ?? null,
                        'period_end' => $data['period_end'] ?? null,
                        'salary_month' => $data['salary_month'] ?? null,
                    ]);

                    // 2. Update Tasks (only for piece-rate)
                    if (!$isMonthly) {
                        foreach ($unpaidTasks as $task) {
                            $task->update([
                                'is_paid' => true,
                                'worker_payroll_id' => $payroll->id,
                            ]);
                        }
                    }

                    // 3. Handle Cash Advance / Kasbon
                    $record->decrement('current_cash_advance', $deduction);

                    if ($netToPay > 0) {
                        Expense::create([
                            'shop_id' => $record->shop_id,
                            'keperluan' => ($isMonthly ? "Gaji Bulanan (Net): " : "Upah Borongan (Net): ") . "{$record->name} (#{$payroll->id})",
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
                            'date' => now(),
                            'description' => "Potong dari " . ($isMonthly ? 'gaji bulanan' : 'upah borongan') . " (#{$payroll->id})",
                            'recorded_by' => auth()->id(),
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title('Pembayaran Selesai')
                        ->body(($isMonthly ? "Gaji " : "Upah ") . "Rp " . number_format($totalWage, 0, ',', '.') . " diproses.")
                        ->actions([
                            Action::make('print_slip')
                                ->label('Cetak Slip')
                                ->color('success')
                                ->icon('heroicon-o-printer')
                                ->url(route('payroll.print', $payroll->id), shouldOpenInNewTab: true)
                        ])
                        ->send();
                });
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
