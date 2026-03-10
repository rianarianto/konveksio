<?php

namespace App\Filament\Resources\Kasbons;

use App\Models\CashAdvance;
use App\Models\User;
use App\Models\Worker;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class KasbonResource extends Resource
{
    protected static ?string $model = CashAdvance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Kasbon';

    protected static ?string $modelLabel = 'Kasbon';

    protected static ?string $pluralModelLabel = 'Kasbon';

    protected static string|\UnitEnum|null $navigationGroup = 'KARYAWAN';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'kasbon';

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['owner', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['cashAdvanceable', 'recorder'])
            ->latest('date');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('employee_name')
                    ->label('Karyawan / Tukang')
                    ->state(function (CashAdvance $record): string {
                        $person = $record->cashAdvanceable;
                        if (!$person)
                            return '-';
                        $type = $record->cash_advanceable_type === 'App\\Models\\Worker' ? '🔨' : '👤';
                        return $type . ' ' . $person->name;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHasMorph('cashAdvanceable', [User::class], fn($sub) => $sub->where('name', 'like', "%{$search}%"))
                                ->orWhereHasMorph('cashAdvanceable', [Worker::class], fn($sub) => $sub->where('name', 'like', "%{$search}%"));
                        });
                    }),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'loan' => 'Kasbon',
                        'repayment' => 'Pelunasan',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'loan' => 'danger',
                        'repayment' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->weight('bold')
                    ->color(fn(CashAdvance $record): string => $record->type === 'loan' ? 'danger' : 'success'),

                TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(40)
                    ->default('-'),

                TextColumn::make('recorder.name')
                    ->label('Dicatat oleh')
                    ->default('-')
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Beri Kasbon')
                    ->modalHeading('Pengajuan Kasbon Baru')
                    ->modalWidth('lg')
                    ->form([
                        Select::make('employee_type')
                            ->label('Tipe Karyawan')
                            ->options([
                                'worker' => '🔨 Tukang (Borongan)',
                                'user' => '👤 Karyawan (Bulanan)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(\Filament\Schemas\Components\Utilities\Set $set) => $set('employee_id', null)),

                        Select::make('employee_id')
                            ->label('Pilih Karyawan')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $type = $get('employee_type');
                                if ($type === 'worker') {
                                    return Worker::where('shop_id', Filament::getTenant()?->id)
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn($w) => [
                                            $w->id => $w->name . ' (Sisa: Rp ' . number_format($w->current_cash_advance, 0, ',', '.') . ' / Limit: Rp ' . number_format($w->max_cash_advance, 0, ',', '.') . ')',
                                        ]);
                                }
                                if ($type === 'user') {
                                    return User::where('shop_id', Filament::getTenant()?->id)
                                        ->whereIn('role', ['admin', 'designer'])
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => $u->name . ' (Sisa: Rp ' . number_format($u->current_cash_advance, 0, ',', '.') . ' / Limit: Rp ' . number_format($u->max_cash_advance, 0, ',', '.') . ')',
                                        ]);
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        TextInput::make('amount')
                            ->label('Nominal Kasbon (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),

                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->native(false)
                            ->default(now()),

                        TextInput::make('note')
                            ->label('Catatan / Alasan')
                            ->maxLength(255),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $type = $data['employee_type'];
                        $id = $data['employee_id'];

                        // Determine polymorphic model
                        if ($type === 'worker') {
                            $person = Worker::findOrFail($id);
                            $data['cash_advanceable_type'] = 'App\\Models\\Worker';
                        } else {
                            $person = User::findOrFail($id);
                            $data['cash_advanceable_type'] = 'App\\Models\\User';
                        }

                        // Validate limit
                        $sisaLimit = $person->max_cash_advance - $person->current_cash_advance;
                        if ($data['amount'] > $sisaLimit) {
                            Notification::make()
                                ->danger()
                                ->title('Kasbon Ditolak')
                                ->body("Sisa limit kasbon {$person->name} hanya Rp " . number_format($sisaLimit, 0, ',', '.'))
                                ->send();
                            throw new \Illuminate\Validation\ValidationException(
                                validator: \Illuminate\Support\Facades\Validator::make([], []),
                            );
                        }

                        $data['cash_advanceable_id'] = $id;
                        $data['type'] = 'loan';
                        $data['shop_id'] = Filament::getTenant()?->id;
                        $data['recorded_by'] = auth()->id();

                        // Update current balance
                        $person->increment('current_cash_advance', $data['amount']);

                        // Auto-record to Expense (Buku Kas Keluar)
                        Expense::create([
                            'shop_id' => $data['shop_id'],
                            'keperluan' => 'Kasbon: ' . $person->name,
                            'amount' => $data['amount'],
                            'expense_date' => $data['date'],
                            'note' => 'Kasbon Karyawan',
                            'recorded_by' => auth()->id(),
                        ]);

                        // Remove helper fields
                        unset($data['employee_type'], $data['employee_id']);

                        return $data;
                    }),

                Action::make('set_limit')
                    ->label('⚙ Atur Limit Kasbon')
                    ->visible(fn() => auth()->user()->role === 'owner')
                    ->color('gray')
                    ->modalHeading('Atur Limit Kasbon')
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\Toggle::make('apply_to_all')
                            ->label('Atur untuk SEMUA Karyawan & Tukang sekaligus?')
                            ->helperText('Jika diaktifkan, limit baru akan diterapkan ke semua tukang (borongan) dan karyawan (bulanan) di toko ini.')
                            ->live()
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, $state) {
                                if ($state) {
                                    $set('limit_employee_type', null);
                                    $set('limit_employee_id', null);
                                }
                            }),

                        Select::make('limit_employee_type')
                            ->label('Tipe Karyawan')
                            ->options([
                                'worker' => '🔨 Tukang (Borongan)',
                                'user' => '👤 Karyawan (Bulanan)',
                            ])
                            ->required(fn(\Filament\Schemas\Components\Utilities\Get $get) => !$get('apply_to_all'))
                            ->hidden(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('apply_to_all'))
                            ->live()
                            ->afterStateUpdated(fn(\Filament\Schemas\Components\Utilities\Set $set) => $set('limit_employee_id', null)),

                        Select::make('limit_employee_id')
                            ->label('Pilih Karyawan')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $type = $get('limit_employee_type');
                                if ($type === 'worker') {
                                    return Worker::where('shop_id', Filament::getTenant()?->id)
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn($w) => [
                                            $w->id => $w->name . ' (Limit saat ini: Rp ' . number_format($w->max_cash_advance, 0, ',', '.') . ')',
                                        ]);
                                }
                                if ($type === 'user') {
                                    return User::where('shop_id', Filament::getTenant()?->id)
                                        ->whereIn('role', ['admin', 'designer'])
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => $u->name . ' (Limit saat ini: Rp ' . number_format($u->max_cash_advance, 0, ',', '.') . ')',
                                        ]);
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required(fn(\Filament\Schemas\Components\Utilities\Get $get) => !$get('apply_to_all'))
                            ->hidden(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('apply_to_all')),

                        TextInput::make('new_limit')
                            ->label('Limit Kasbon Baru (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                    ])
                    ->action(function (array $data): void {
                        $shopId = Filament::getTenant()?->id;
                        $newLimit = $data['new_limit'];

                        if ($data['apply_to_all'] ?? false) {
                            // Update All Workers in current shop
                            Worker::where('shop_id', $shopId)->update(['max_cash_advance' => $newLimit]);

                            // Update All Admin/Designer Users in current shop
                            User::where('shop_id', $shopId)
                                ->whereIn('role', ['admin', 'designer'])
                                ->update(['max_cash_advance' => $newLimit]);

                            Notification::make()
                                ->success()
                                ->title('Limit Massal Diperbarui')
                                ->body("Limit kasbon SEMUA karyawan & tukang telah diubah menjadi Rp " . number_format($newLimit, 0, ',', '.'))
                                ->send();
                        } else {
                            $type = $data['limit_employee_type'];
                            $id = $data['limit_employee_id'];

                            if ($type === 'worker') {
                                $person = Worker::findOrFail($id);
                            } else {
                                $person = User::findOrFail($id);
                            }

                            $person->update(['max_cash_advance' => $newLimit]);

                            Notification::make()
                                ->success()
                                ->title('Limit Berhasil Diperbarui')
                                ->body("Limit kasbon {$person->name} diubah menjadi Rp " . number_format($newLimit, 0, ',', '.'))
                                ->send();
                        }
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'loan' => 'Kasbon',
                        'repayment' => 'Pelunasan',
                    ]),
            ])
            ->defaultSort('date', 'desc')
            ->heading('Riwayat Kasbon Karyawan')
            ->description('Catatan kasbon dan pelunasan semua karyawan & tukang.')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageKasbons::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Creation handled via headerAction
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
