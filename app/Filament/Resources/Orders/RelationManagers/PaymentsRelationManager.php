<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('amount')
                ->label('Jumlah Dibayar (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(1),

            DatePicker::make('payment_date')
                ->label('Tanggal Pembayaran')
                ->required()
                ->default(now()),

            Select::make('payment_method')
                ->label('Metode Pembayaran')
                ->options([
                    'cash'     => '💵 Cash',
                    'transfer' => '🏦 Transfer Bank',
                    'qris'     => '📱 QRIS',
                ])
                ->required()
                ->default('cash'),

            TextInput::make('note')
                ->label('Catatan')
                ->placeholder('Contoh: DP 1, Cicilan 2, Pelunasan...')
                ->maxLength(255),

            FileUpload::make('proof_image')
                ->label('Bukti Transfer / Pembayaran')
                ->image()
                ->imagePreviewHeight('150')
                ->disk('public')
                ->directory('payments/proofs')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->helperText('Upload foto bukti transfer atau struk pembayaran (maks 5MB)'),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([

                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'transfer' => '🏦 Transfer Bank',
                        'qris'     => '📱 QRIS',
                        default    => '💵 Cash',
                    })
                    ->color(fn($state) => match($state) {
                        'transfer' => 'info',
                        'qris'     => 'warning',
                        default    => 'success',
                    }),

                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—'),

                ImageColumn::make('proof_image')
                    ->label('Bukti')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl(null),

                TextColumn::make('recorder.name')
                    ->label('Dicatat Oleh')
                    ->placeholder('—')
                    ->color('gray'),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Pembayaran')
                    ->color('success')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('payment_date', 'asc');
    }
}
