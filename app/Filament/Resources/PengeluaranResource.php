<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengeluaranResource\Pages;
use App\Models\Expense;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class PengeluaranResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static ?string $navigationLabel = 'Kas Keluar / Pengeluaran';

    protected static ?string $modelLabel = 'Pengeluaran';

    protected static ?string $pluralModelLabel = 'Pengeluaran';

    protected static string|\UnitEnum|null $navigationGroup = 'KEUANGAN';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
                    'Bahan Baku' => 'Bahan Baku',
                    'Operasional' => 'Operasional',
                    'Gaji / Upah' => 'Gaji / Upah',
                    'Transport' => 'Transport',
                    'Alat & Mesin' => 'Alat & Mesin',
                    'Kasbon Karyawan' => 'Kasbon Karyawan',
                    'Lainnya' => 'Lainnya',
                ])
                ->placeholder('Pilih kategori...'),

            FileUpload::make('proof_image')
                ->label('Foto Bukti (Opsional)')
                ->image()
                ->disk('public')
                ->directory('expense-proofs')
                ->imagePreviewHeight('120')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('recorder.name')
                    ->label('Dicatat oleh')
                    ->default('-'),

                ImageColumn::make('proof_image')
                    ->label('Bukti')
                    ->state(fn($record) => $record->proof_image ? asset('storage/' . $record->proof_image) : null)
                    ->disk(null)
                    ->width(48)
                    ->height(48)
                    ->defaultImageUrl(null),
            ])
            ->filters([
                SelectFilter::make('note')
                    ->label('Kategori')
                    ->options([
                        'Bahan Baku' => 'Bahan Baku',
                        'Operasional' => 'Operasional',
                        'Gaji / Upah' => 'Gaji / Upah',
                        'Transport' => 'Transport',
                        'Alat & Mesin' => 'Alat & Mesin',
                        'Kasbon Karyawan' => 'Kasbon Karyawan',
                        'Lainnya' => 'Lainnya',
                    ]),
                Filter::make('expense_date')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $v) => $q->whereDate('expense_date', '>=', $v))
                            ->when($data['until'], fn($q, $v) => $q->whereDate('expense_date', '<=', $v));
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Pengeluaran')
                    ->modalHeading('Tambah Pengeluaran')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['shop_id'] = Filament::getTenant()?->id;
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengeluaran::route('/'),
        ];
    }
}
