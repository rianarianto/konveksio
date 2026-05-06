<?php

namespace App\Filament\Resources;

use App\Models\WorkerPayroll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class WorkerPayrollHistoryResource extends Resource
{
    protected static ?string $model = WorkerPayroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Riwayat Gaji';

    protected static ?string $modelLabel = 'Riwayat Gaji';

    protected static ?string $pluralModelLabel = 'Riwayat Gaji';

    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('worker.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('total_wage')
                    ->label('Upah Kotor')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('kasbon_deduction')
                    ->label('Potongan Kasbon')
                    ->money('IDR')
                    ->color('danger'),

                TextColumn::make('net_amount')
                    ->label('Upah Bersih')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('recorded_by_name')
                    ->label('Dicatat Oleh')
                    ->state(fn(WorkerPayroll $record) => $record->recorder->name ?? '-')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('worker_id')
                    ->label('Karyawan')
                    ->relationship('worker', 'name'),
            ])
            ->actions([
                Action::make('print')
                    ->label('Cetak Slip')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn(WorkerPayroll $record) => route('payroll.print', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\WorkerPayrollHistoryResource\Pages\ListWorkerPayrollHistories::route('/'),
        ];
    }
}
