<?php

namespace App\Filament\Resources\DesignTasks;

use App\Filament\Resources\DesignTasks\Pages\ManageDesignTasks;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;

class DesignTaskResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Meja Desain';
    protected static ?string $modelLabel = 'Tugas Desain';
    protected static ?string $slug = 'design-tasks';

    protected static string|\UnitEnum|null $navigationGroup = 'PRODUKSI';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;
    protected static ?string $tenantOwnershipRelationshipName = 'orderShop';

    public static function canAccess(): bool
    {
        // Akses untuk Designer dan Owner
        return in_array(auth()->user()->role, ['designer', 'owner']);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Override and do nothing.
        // This prevents Filament from attempting to auto-save the `orderShop`
        // HasOneThrough relation whenever an OrderItem is created globally,
        // which would cause a "Call to undefined method HasOneThrough::save()" error.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['order.customer'])
            ->whereIn('design_status', ['pending', 'uploaded']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Rincian Teknis Produk')
                        ->schema([
                            Placeholder::make('technical_specs')
                                ->label(false)
                                ->content(function ($record): HtmlString {
                                    if (!$record)
                                        return new HtmlString('');

                                    $html = '<div class="text-sm p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100">';

                                    $name = htmlspecialchars($record->product_name ?? 'Produk Tak Bernama');
                                    $cat = $record->production_category ?? 'produksi';
                                    $details = $record->size_and_request_details ?? [];

                                    $html .= '<h4 class="mb-2 font-semibold text-base">' . $name . '</h4>';

                                    if ($cat === 'custom') {
                                        $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                        $html .= '<p class="mb-1"><strong>Bahan:</strong> ' . $bahan . '</p>';

                                        $sablon = $details['sablon_bordir'] ?? [];
                                        if (count($sablon) > 0) {
                                            $html .= '<p class="mt-2 mb-1 font-semibold">Sablon / Bordir:</p>';
                                            $html .= '<ul class="mb-2 pl-5 list-disc">';
                                            foreach ($sablon as $s) {
                                                $j = htmlspecialchars($s['jenis'] ?? '');
                                                $l = htmlspecialchars($s['lokasi'] ?? '');
                                                $u = htmlspecialchars($s['ukuran_cmxcm'] ?? '');
                                                $html .= '<li>' . $j . ' di ' . $l . ($u ? ' (' . $u . ')' : '') . '</li>';
                                            }
                                            $html .= '</ul>';
                                        }

                                        $ukurans = $details['detail_custom'] ?? [];
                                        if (count($ukurans) > 0) {
                                            $html .= '<p class="mt-2 mb-1 font-semibold">Referensi Ukuran Custom:</p>';
                                            $html .= '<div class="max-h-[200px] overflow-y-auto pr-2">';
                                            foreach ($ukurans as $u) {
                                                $person = htmlspecialchars($u['nama'] ?? 'Tanpa Nama');
                                                $ld = htmlspecialchars($u['LD'] ?? '-');
                                                $lp = htmlspecialchars($u['LP'] ?? '-');
                                                $html .= '<div class="text-sm mb-1 pb-1 border-b border-dotted border-gray-200 dark:border-gray-700">';
                                                $html .= '<strong>' . $person . '</strong> — LD: ' . $ld . ', LP: ' . $lp;
                                                $html .= '</div>';
                                            }
                                            $html .= '</div>';
                                        }

                                    } elseif ($cat === 'non_produksi') {
                                        $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                        $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');
                                        $html .= '<p class="mb-1"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                        $html .= '<p class="mb-1"><strong>Lokasi:</strong> ' . $l . '</p>';
                                    } elseif ($cat === 'jasa') {
                                        $html .= '<p class="mb-1"><strong>Catatan Jasa:</strong> Item ini merupakan pengerjaan jasa murni.</p>';
                                    } else {
                                        // produksi biasa
                                        $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                        $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                        $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');

                                        $html .= '<p class="mb-1"><strong>Bahan:</strong> ' . $bahan . '</p>';
                                        $html .= '<p class="mb-1"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                        $html .= '<p class="mb-1"><strong>Lokasi:</strong> ' . $l . '</p>';
                                    }

                                    $html .= '</div>';
                                    return new HtmlString($html);
                                })
                        ])
                ])->columnSpan(1),

                Group::make([
                    Section::make('Upload File Final Desain')
                        ->description('Silakan unggah file referensi atau panduan desain akhir untuk produk ini saja.')
                        ->schema([
                            FileUpload::make('design_image')
                                ->label('Artwork / Desain Final')
                                ->image()
                                ->imageEditor()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->disk('public')
                                ->directory('designs')
                                ->downloadable()
                                ->openable()
                                ->required()
                                ->helperText('Setelah disimpan, status desain otomatis berubah menjadi Approved dan masuk antrian produksi.'),
                        ])
                ])->columnSpan(1),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('product_name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('production_category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state) => [
                        50 => '#F2E6FF',
                        500 => '#8000FF',
                        600 => '#8000FF',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'non_produksi' => 'Non Produksi',
                        default => ucfirst($state),
                    }),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('design_status')
                    ->label('Status Desain')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'uploaded' => 'info',
                        'approved' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->label('Kerjakan Desain')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->using(function (OrderItem $record, array $data): OrderItem {
                        // Logika Save Item Level: Otomatis ubah status saat file disimpan
                        $data['design_status'] = 'approved';
                        $record->update($data);

                        // Hanya perbarui status item ini saja, tidak menyentuh status Order global.
                        // Order baru akan berubah jadi 'Antrian' saat Admin membagi tugas di sini.
            
                        return $record;
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
            )
            ->heading('Antrian Tugas Desain');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDesignTasks::route('/'),
        ];
    }

    // Blokir fungsi tambah & hapus karena Desainer hanya mengunggah file
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}

