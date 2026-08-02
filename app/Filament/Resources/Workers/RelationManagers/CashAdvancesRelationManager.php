<?php

namespace App\Filament\Resources\Workers\RelationManagers;

use App\Models\CashAdvance;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

class CashAdvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'cashAdvances';

    protected static ?string $title = 'Riwayat Kasbon';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'loan' => '💸 Pinjaman (Kasbon)',
                        'repayment' => '📥 Pelunasan',
                    ])
                    ->required()
                    ->default('loan'),
                Forms\Components\TextInput::make('amount')
                    ->label('Nominal (Rp)')
                    ->numeric()
                    ->required()
                    ->minValue(1000),
                Forms\Components\Textarea::make('note')
                    ->label('Catatan')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'loan', 'pinjaman' => 'warning',
                        'repayment', 'pelunasan' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'loan', 'pinjaman' => '💸 Pinjaman',
                        'repayment', 'pelunasan' => '📥 Pelunasan',
                        default => $state,
                    }),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->weight('bold')
                    ->color(fn ($record) => in_array($record->type, ['repayment', 'pelunasan']) ? 'success' : 'danger'),

                TextColumn::make('note')
                    ->label('Catatan')
                    ->wrap(),

                TextColumn::make('recorder.name')
                    ->label('Dicatat Oleh')
                    ->default('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'loan' => '💸 Pinjaman',
                        'repayment' => '📥 Pelunasan',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kasbon')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['shop_id'] = \Filament\Facades\Filament::getTenant()->id;
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (CashAdvance $record): void {
                        // Update current_cash_advance of the worker
                        $worker = $record->cashAdvanceable;
                        if ($worker) {
                            if (in_array($record->type, ['loan', 'pinjaman'])) {
                                $worker->increment('current_cash_advance', $record->amount);
                            } else {
                                $worker->decrement('current_cash_advance', min($worker->current_cash_advance, $record->amount));
                            }
                        }
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->before(function (CashAdvance $record): void {
                        // Revert current_cash_advance of the worker before deleting
                        $worker = $record->cashAdvanceable;
                        if ($worker) {
                            if (in_array($record->type, ['loan', 'pinjaman'])) {
                                $worker->decrement('current_cash_advance', min($worker->current_cash_advance, $record->amount));
                            } else {
                                $worker->increment('current_cash_advance', $record->amount);
                            }
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc');
    }
}
