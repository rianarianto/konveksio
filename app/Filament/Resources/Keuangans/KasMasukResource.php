<?php

namespace App\Filament\Resources\Keuangans;

use App\Filament\Resources\Keuangans\Pages;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Expense;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Filament\Facades\Filament;

class KasMasukResource extends Resource
{
    protected static ?string $model = Order::class; // Changed to Order to solve tenancy scoping (as Order belongs to Shop)

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Kas Masuk & Piutang';

    protected static ?string $modelLabel = 'Keuangan';

    protected static ?string $pluralModelLabel = 'Keuangan';

    protected static string|\UnitEnum|null $navigationGroup = 'KEUANGAN';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return in_array($user->role, ['owner', 'admin']);
    }

    // ── Form untuk Tambah/Edit Pengeluaran ───────────────────────────────────
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKasMasuk::route('/'),
        ];
    }
}
