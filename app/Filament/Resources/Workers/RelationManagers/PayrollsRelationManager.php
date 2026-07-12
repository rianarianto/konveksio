<?php

namespace App\Filament\Resources\Workers\RelationManagers;

use App\Models\WorkerPayroll;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $title = 'Riwayat Slip Gaji';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_date')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date('d M Y')
                    ->sortable(),

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

                TextColumn::make('recorder.name')
                    ->label('Dicatat Oleh')
                    ->color('gray')
                    ->default('-'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Bayar')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('total_wage')
                            ->label('Upah Kotor')
                            ->numeric()
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('kasbon_deduction')
                            ->label('Potongan Kasbon')
                            ->numeric()
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('net_amount')
                            ->label('Upah Bersih')
                            ->numeric()
                            ->disabled(),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ])
            ->bulkActions([])
            ->defaultSort('payment_date', 'desc');
    }
}
