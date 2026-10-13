<?php

namespace App\Filament\Resources\WageCorrections;

use App\Filament\Resources\WageCorrections\Pages\ListWageCorrections;
use App\Models\WageCorrectionRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WageCorrectionResource extends Resource
{
    protected static ?string $model = WageCorrectionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Riwayat Koreksi Upah';

    protected static ?string $modelLabel = 'Riwayat Koreksi Upah';

    protected static ?string $pluralModelLabel = 'Riwayat Koreksi Upah';

    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'productionTask.assignedWorker',
                'productionTask.orderItem.order',
                'requester',
                'reviewer',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('productionTask.assignedWorker.name')
                    ->label('Pekerja')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->default('-'),

                TextColumn::make('task_detail')
                    ->label('Pesanan & Tahap')
                    ->state(function (WageCorrectionRequest $record): string {
                        $orderNum = $record->productionTask?->orderItem?->order?->order_number ?? '-';
                        $stage = $record->productionTask?->stage_name ?? '-';
                        return "{$orderNum} ({$stage})";
                    })
                    ->description(fn (WageCorrectionRequest $record): string => 'Qty: ' . ($record->productionTask?->quantity ?? 0) . ' pcs'),

                TextColumn::make('old_wage_amount')
                    ->label('Upah Lama')
                    ->money('IDR')
                    ->color('gray'),

                TextColumn::make('new_wage_amount')
                    ->label('Upah Baru')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('selisih')
                    ->label('Selisih')
                    ->state(function (WageCorrectionRequest $record): string {
                        $diff = $record->new_wage_amount - $record->old_wage_amount;
                        $sign = $diff > 0 ? '+' : '';
                        return $sign . 'Rp ' . number_format($diff, 0, ',', '.');
                    })
                    ->badge()
                    ->color(fn (WageCorrectionRequest $record): string => ($record->new_wage_amount >= $record->old_wage_amount) ? 'success' : 'danger'),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(35)
                    ->tooltip(fn (WageCorrectionRequest $record): ?string => $record->reason)
                    ->wrap(),

                TextColumn::make('requester.name')
                    ->label('Diajukan Oleh')
                    ->default('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'  => 'Menunggu Review',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default    => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('reviewer.name')
                    ->label('Direview Oleh')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->default('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Menunggu Review',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Rincian')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading('Rincian Riwayat Koreksi Upah')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->infolist(function (WageCorrectionRequest $record) {
                        return [
                            \Filament\Schemas\Components\Section::make('Informasi Tugas & Pekerja')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('productionTask.assignedWorker.name')
                                        ->label('Nama Pekerja'),
                                    \Filament\Infolists\Components\TextEntry::make('productionTask.orderItem.order.order_number')
                                        ->label('Nomor Pesanan'),
                                    \Filament\Infolists\Components\TextEntry::make('productionTask.stage_name')
                                        ->label('Tahap Produksi'),
                                    \Filament\Infolists\Components\TextEntry::make('productionTask.quantity')
                                        ->label('Qty Dikerjakan')
                                        ->suffix(' pcs'),
                                ])
                                ->columns(2),

                            \Filament\Schemas\Components\Section::make('Perbandingan Upah')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('old_wage_per_pcs')
                                        ->label('Tarif Lama (per pcs)')
                                        ->money('IDR'),
                                    \Filament\Infolists\Components\TextEntry::make('new_wage_per_pcs')
                                        ->label('Tarif Baru (per pcs)')
                                        ->money('IDR'),
                                    \Filament\Infolists\Components\TextEntry::make('old_wage_amount')
                                        ->label('Total Upah Lama')
                                        ->money('IDR'),
                                    \Filament\Infolists\Components\TextEntry::make('new_wage_amount')
                                        ->label('Total Upah Baru')
                                        ->money('IDR'),
                                ])
                                ->columns(2),

                            \Filament\Schemas\Components\Section::make('Keterangan & Audit Trail')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('reason')
                                        ->label('Alasan Koreksi')
                                        ->columnSpanFull(),
                                    \Filament\Infolists\Components\TextEntry::make('requester.name')
                                        ->label('Diajukan Oleh'),
                                    \Filament\Infolists\Components\TextEntry::make('created_at')
                                        ->label('Waktu Pengajuan')
                                        ->dateTime('d M Y, H:i'),
                                    \Filament\Infolists\Components\TextEntry::make('reviewer.name')
                                        ->label('Direview Oleh')
                                        ->visible(fn () => !empty($record->reviewed_by)),
                                    \Filament\Infolists\Components\TextEntry::make('reviewed_at')
                                        ->label('Waktu Review')
                                        ->dateTime('d M Y, H:i')
                                        ->visible(fn () => !empty($record->reviewed_at)),
                                    \Filament\Infolists\Components\TextEntry::make('rejection_reason')
                                        ->label('Alasan Penolakan')
                                        ->visible(fn () => !empty($record->rejection_reason))
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ];
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWageCorrections::route('/'),
        ];
    }
}
