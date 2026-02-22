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

    protected static bool $isScopedToTenant = true;

    public static function canAccess(): bool
    {
        // Hanya bisa diakses oleh Designer
        return auth()->user()->role === 'designer';
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
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
                                    if (!$record) return new HtmlString('');

                                    $html = '<div style="font-family: inherit; font-size: 14px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;">';
                                    
                                    $name = htmlspecialchars($record->product_name ?? 'Produk Tak Bernama');
                                    $cat = $record->production_category ?? 'produksi';
                                    $details = $record->size_and_request_details ?? [];
                                    
                                    $html .= '<h4 style="margin: 0 0 8px 0; font-weight: 600; color: #111827; font-size: 16px;">' . $name . '</h4>';

                                    if ($cat === 'custom') {
                                        $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Bahan:</strong> ' . $bahan . '</p>';
                                        
                                        $sablon = $details['sablon_bordir'] ?? [];
                                        if (count($sablon) > 0) {
                                            $html .= '<p style="margin: 8px 0 4px 0; font-weight: 600;">Sablon / Bordir:</p>';
                                            $html .= '<ul style="margin: 0 0 8px 0; padding-left: 20px;">';
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
                                            $html .= '<p style="margin: 8px 0 4px 0; font-weight: 600;">Referensi Ukuran Custom:</p>';
                                            $html .= '<div style="max-height: 200px; overflow-y: auto; padding-right: 8px;">';
                                            foreach ($ukurans as $u) {
                                                $person = htmlspecialchars($u['nama'] ?? 'Tanpa Nama');
                                                $ld = htmlspecialchars($u['LD'] ?? '-');
                                                $lp = htmlspecialchars($u['LP'] ?? '-');
                                                $html .= '<div style="font-size: 13px; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dotted #e5e7eb;">';
                                                $html .= '<strong>' . $person . '</strong> — LD: ' . $ld . ', LP: ' . $lp;
                                                $html .= '</div>';
                                            }
                                            $html .= '</div>';
                                        }
                                        
                                    } elseif ($cat === 'non_produksi') {
                                        $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                        $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Lokasi:</strong> ' . $l . '</p>';
                                    } elseif ($cat === 'jasa') {
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Catatan Jasa:</strong> Item ini merupakan pengerjaan jasa murni.</p>';
                                    } else {
                                        // produksi biasa
                                        $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                        $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                        $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');
                                        
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Bahan:</strong> ' . $bahan . '</p>';
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                        $html .= '<p style="margin: 0 0 4px 0;"><strong>Lokasi:</strong> ' . $l . '</p>';
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
                                ->directory('designs')
                                ->visibility('private')
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
                    ->color(fn (string $state): string => match ($state) {
                        'produksi' => 'primary',
                        'custom' => 'warning',
                        'non_produksi' => 'gray',
                        'jasa' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('design_status')
                    ->label('Status Desain')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'  => 'warning',
                        'uploaded' => 'info',
                        'approved' => 'success',
                        default    => 'gray',
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

                        // Cek apakah semua item di parent order sudah approved
                        $parentOrder = Order::find($record->order_id);
                        if ($parentOrder) {
                            $totalItems = $parentOrder->orderItems()->count();
                            $approvedItems = $parentOrder->orderItems()->where('design_status', 'approved')->count();

                            // Jika semua item sudah di-approve, ubah status Order
                            if ($totalItems > 0 && $totalItems === $approvedItems) {
                                if ($parentOrder->status === 'diterima') {
                                    $parentOrder->update(['status' => 'antrian']);
                                }
                            }
                        }

                        return $record;
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn (Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
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

