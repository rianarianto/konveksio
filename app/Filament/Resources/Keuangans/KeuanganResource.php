<?php

namespace App\Filament\Resources\Keuangans;

use App\Filament\Resources\Keuangans\Pages;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Filament\Facades\Filament;

class KeuanganResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Keuangan';

    protected static ?string $modelLabel = 'Keuangan';

    protected static ?string $pluralModelLabel = 'Keuangan';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return in_array($user->role, ['owner', 'admin']);
    }

    // ── Form untuk Tambah/Edit Pengeluaran ───────────────────────────────────
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

    // ── Tabel Pengeluaran (default tab 3) ───────────────────────────────────
    public static function table(Table $table): Table
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
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('+ Tambah Pengeluaran')
                    ->modalHeading('Tambah Pengeluaran')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['shop_id'] = Filament::getTenant()?->id;
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()->modalHeading('Edit Pengeluaran'),
                DeleteAction::make(),
            ])
            ->defaultSort('expense_date', 'desc')
            ->emptyStateHeading('Belum ada pengeluaran')
            ->emptyStateDescription('Klik "+ Tambah Pengeluaran" untuk mencatat pengeluaran operasional.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeuangan::route('/'),
        ];
    }
}
