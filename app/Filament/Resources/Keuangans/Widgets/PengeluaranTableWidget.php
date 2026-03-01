<?php

namespace App\Filament\Resources\Keuangans\Widgets;

use App\Models\Expense;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;

class PengeluaranTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Daftar Pengeluaran Operasional';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Expense::query()
                    ->where('shop_id', Filament::getTenant()?->id)
                    ->latest('expense_date')
            )
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('keperluan')
                    ->label('Keperluan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('note')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('recorder.name')
                    ->label('Dicatat oleh')
                    ->default('-'),

                ImageColumn::make('proof_image')
                    ->label('Bukti')
                    ->disk('public')
                    ->width(48)
                    ->height(48)
                    ->defaultImageUrl(null),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Pengeluaran')
                    ->modalHeading('Tambah Pengeluaran')
                    ->form([
                        TextInput::make('keperluan')
                            ->label('Keperluan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('amount')
                            ->label('Nominal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),

                        DatePicker::make('expense_date')
                            ->label('Tanggal')
                            ->required()
                            ->native(false)
                            ->default(now()),

                        Select::make('note')
                            ->label('Kategori')
                            ->options([
                                'Bahan Baku'       => 'Bahan Baku',
                                'Operasional'      => 'Operasional',
                                'Gaji / Upah'      => 'Gaji / Upah',
                                'Transport'        => 'Transport',
                                'Alat & Mesin'     => 'Alat & Mesin',
                                'Lainnya'          => 'Lainnya',
                            ])
                            ->placeholder('Pilih kategori...'),

                        FileUpload::make('proof_image')
                            ->label('Foto Bukti (Opsional)')
                            ->image()
                            ->disk('public')
                            ->directory('expense-proofs')
                            ->imagePreviewHeight('120')
                            ->columnSpanFull(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['shop_id']     = Filament::getTenant()?->id;
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    })
                    ->action(function (array $data, $livewire) {
                        Expense::create($data);
                        $livewire->dispatch('refreshStats'); // emit event for widget
                    }),
            ])
            ->actions([
                EditAction::make()->modalHeading('Edit Pengeluaran')
                    ->form([
                        TextInput::make('keperluan')
                            ->label('Keperluan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('amount')
                            ->label('Nominal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),

                        DatePicker::make('expense_date')
                            ->label('Tanggal')
                            ->required()
                            ->native(false),

                        Select::make('note')
                            ->label('Kategori')
                            ->options([
                                'Bahan Baku'       => 'Bahan Baku',
                                'Operasional'      => 'Operasional',
                                'Gaji / Upah'      => 'Gaji / Upah',
                                'Transport'        => 'Transport',
                                'Alat & Mesin'     => 'Alat & Mesin',
                                'Lainnya'          => 'Lainnya',
                            ]),

                        FileUpload::make('proof_image')
                            ->label('Foto Bukti (Opsional)')
                            ->image()
                            ->disk('public')
                            ->directory('expense-proofs')
                            ->imagePreviewHeight('120')
                            ->columnSpanFull(),
                    ])->action(function ($record, array $data, $livewire) {
                        $record->update($data);
                        $livewire->dispatch('refreshStats');
                    }),
                DeleteAction::make()->after(function ($livewire) {
                    $livewire->dispatch('refreshStats');
                }),
            ])
            ->defaultSort('expense_date', 'desc')
            ->emptyStateHeading('Belum ada pengeluaran')
            ->emptyStateDescription('Klik "+ Tambah Pengeluaran" untuk mencatat pengeluaran operasional.');
    }
}
