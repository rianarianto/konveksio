<?php

namespace App\Filament\Resources\ControlProduksis;

use App\Filament\Resources\ControlProduksis\Pages\ManageControlProduksis;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionStage;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Resources\ControlProduksis\Pages\AturTugasProduksi;

class ControlProduksiResource extends Resource
{
    protected static ?string $model = \App\Models\OrderItem::class;

    protected static ?string $slug = 'control-produksi';

    protected static string|\UnitEnum|null $navigationGroup = 'PRODUKSI';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'designer', 'owner']);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getModelLabel(): string
    {
        return 'Tugas Produksi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Control Produksi';
    }

    protected static bool $isScopedToTenant = true;

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Override and do nothing.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('order_items.design_status', 'approved')
            ->with(['order.customer', 'productionTasks'])
            ->whereIn('order_items.id', function (\Illuminate\Database\Query\Builder $query) {
                $query->selectRaw('MIN(id)')
                    ->from('order_items')
                    ->where('design_status', 'approved')
                    ->groupBy('order_id', 'product_name');
            });
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->doesntHave('productionTasks')
            ->count();
            
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The form schema is now handled via Page Actions in ManageControlProduksis
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(OrderItem $record): string => match ($record->production_category) {
                        'custom' => '🧵 Konveksi (Ukur Badan)',
                        'non_produksi' => '📦 Baju Jadi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Konveksi',
                    }),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->getStateUsing(
                        fn(OrderItem $record): int => (function() use ($record) {
                            $details = $record->size_and_request_details ?? [];
                            $query = OrderItem::where('order_id', $record->order_id)
                                ->where('product_name', $record->product_name)
                                ->where('bahan_id', $record->bahan_id)
                                ->where('design_status', 'approved');
                                
                            $keys = ['gender', 'sleeve_model', 'pocket_model', 'button_model', 'is_tunic', 'sablon_jenis', 'sablon_lokasi'];
                            foreach ($keys as $k) {
                                $query->where('size_and_request_details->' . $k, $details[$k] ?? null);
                            }
                            
                            return $query->sum('quantity');
                        })()
                    )
                    ->numeric()
                    ->sortable(['quantity']),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('progress_status')
                    ->label('Status Produksi')
                    ->badge()
                    ->state(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->count() === 0)
                            return 'Belum Diatur';
                        if ($tasks->where('status', '!=', 'done')->count() === 0)
                            return 'Selesai';
                        if ($tasks->where('status', 'in_progress')->count() > 0)
                            return 'Diproses';
                        return 'Antrian';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diatur' => 'gray',
                        'Antrian' => 'warning',
                        'Diproses' => 'info',
                        'Selesai' => 'success',
                        default => 'gray',
                    })
                    ->description(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->isEmpty())
                            return null;
                        $activeTask = $tasks->firstWhere('status', 'in_progress');
                        if ($activeTask) {
                            return '🔨 ' . $activeTask->stage_name . ' — ' . ($activeTask->assignedTo?->name ?? '-');
                        }
                        $pendingTask = $tasks->where('status', 'pending')->sortBy('id')->first();
                        if ($pendingTask) {
                            return '⏳ Menunggu: ' . $pendingTask->stage_name;
                        }
                        return null;
                    }),

            ])

            ->groups([
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(function (Model $record): HtmlString {
                        $order = $record->order;
                        $prefix = $order->is_express
                            ? '<span style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-right:6px;">⚡ EXPRESS</span>'
                            : '';
                        return new HtmlString($prefix . $order->order_number . ' — ' . ($order->customer->name ?? 'Tanpa Nama'));
                    })
                    ->collapsible(),

                TableGroup::make('production_category')
                    ->label('Kategori Pesanan')
                    ->getTitleFromRecordUsing(fn(OrderItem $record): string => match ($record->production_category) {
                        'custom' => '🧵 Konveksi (Ukur Badan)',
                        'non_produksi' => '📦 Baju Jadi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Konveksi',
                    })
                    ->collapsible(),

            ])
            ->defaultGroup('order.order_number')
            ->modifyQueryUsing(
                fn($query) => $query
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->select('order_items.*')
                    ->orderByRaw('orders.is_express DESC')
                    ->orderBy('orders.deadline', 'asc')
            )
            ->filters([
                // Filters handled by Tabs in Manage page
            ])
            ->actions([
                ActionGroup::make([
                Action::make('update_progress')
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->where('status', '!=', 'done')->count() === 0)
                    ->label('Update Progress')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->modalWidth('2xl')
                    ->modalHeading(fn(OrderItem $record) => 'Kelola Tugas: ' . ($record->product_name ?? 'Item'))
                    ->modalDescription('Klik tombol pada setiap baris untuk memperbarui status tugas masing-masing karyawan.')
                    ->fillForm(function (OrderItem $record) {
                        return ['item_id' => $record->id];
                    })
                    ->form(function (OrderItem $record) {
                        return [
                            Placeholder::make('task_manager')
                                ->hiddenLabel()
                                ->content(fn () => new \Illuminate\Support\HtmlString(
                                    \Illuminate\Support\Facades\Blade::render("@livewire('task-status-manager', ['orderItemId' => {$record->id}])")
                                )),
                        ];
                    })
                    ->action(function (array $data, OrderItem $record) {
                        // Action utama form ini hanya sebagai re-render / reload
                        // Update status dilakukan via dedicated route (link tombol per baris)
                    }),

                Action::make('cetak_spk')
                    ->label('Cetak SPK')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(function (OrderItem $record) {
                        $item = $record;
                        
                        // Aggregate data for PDF
                        $totalQuantity = OrderItem::where('order_id', $item->order_id)
                            ->where('product_name', $item->product_name)
                            ->where('design_status', 'approved')
                            ->sum('quantity');

                        // Aggregate sizes
                        $allGroupItems = OrderItem::where('order_id', $item->order_id)
                            ->where('product_name', $item->product_name)
                            ->where('design_status', 'approved')
                            ->get();

                        $sizes = [];
                        $specGroups = [];

                        foreach ($allGroupItems as $gi) {
                            $d = $gi->size_and_request_details ?? [];
                            
                            // Essential specs for grouping
                            $gen = $d['gender'] ?? 'L';
                            $slv = strtoupper($d['sleeve_model'] ?? 'PENDEK');
                            $pck = strtoupper(str_replace('_', ' ', $d['pocket_model'] ?? 'TANPA SAKU'));
                            $btn = strtoupper($d['button_model'] ?? 'BIASA');
                            $tun = !empty($d['is_tunic']) ? 'TUNIK' : 'STANDAR';
                            
                            // Detailed Sablon/Bordir info
                            $sbList = [];
                            if (!empty($d['sablon_bordir'])) {
                                foreach ($d['sablon_bordir'] as $sb) {
                                    $sbList[] = strtoupper($sb['jenis'] ?? '') . " (" . strtoupper($sb['lokasi'] ?? '') . ")";
                                }
                            }
                            // Also check for individual fields if sablon_bordir is empty or just as additional
                            if (!empty($d['sablon_jenis'])) {
                                $sbList[] = strtoupper($d['sablon_jenis']) . (!empty($d['sablon_lokasi']) ? " (" . strtoupper($d['sablon_lokasi']) . ")" : "");
                            }
                            
                            $sbList = array_unique($sbList);
                            sort($sbList);
                            $sbStr = implode(' | ', $sbList);
                            
                            // Additional requests info
                            $reqList = [];
                            if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                                foreach ($d['request_tambahan'] as $rt) {
                                    $reqList[] = strtoupper($rt['jenis'] ?? '') . ": " . ($rt['keterangan'] ?? '');
                                }
                            }
                            sort($reqList);
                            $reqStr = implode(' | ', $reqList);

                            $groupKey = "{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$sbStr}|{$reqStr}";
                            
                            if (!isset($specGroups[$groupKey])) {
                                $specGroups[$groupKey] = [
                                    'gender' => $gen === 'L' ? 'PRIA' : 'WANITA',
                                    'sleeve' => $slv,
                                    'pocket' => $pck,
                                    'button' => $btn,
                                    'tunic' => $tun,
                                    'sablon_bordir' => $sbList,
                                    'requests' => $reqList,
                                    'total_qty' => 0,
                                    'sizes' => [],
                                ];
                            }
                            
                            $specGroups[$groupKey]['total_qty'] += $gi->quantity;
                            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                            $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;
                        }

                        $pdf = Pdf::loadView('pdf.spk-produksi', [
                            'record' => $record->load(['order.customer', 'productionTasks.assignedTo', 'bahan.material']),
                            'totalQuantity' => $totalQuantity,
                            'sizes' => $sizes,
                            'specGroups' => $specGroups,
                            'allGroupItems' => $allGroupItems,
                        ]);

                        $filename = 'SPK-' . $record->order->order_number . '-' . \Illuminate\Support\Str::slug($record->product_name) . '.pdf';
                        
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, $filename);
                    }),



                Action::make('atur_tugas')
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->exists() && $record->productionTasks()->where('status', '!=', 'done')->count() === 0)
                    ->label(fn(OrderItem $record) => $record->productionTasks()->exists() ? 'Edit Tugas' : 'Atur Tugas')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (OrderItem $record): string => AturTugasProduksi::getUrl(['record' => $record])),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi')
                    ->color('gray')
                    ->button()
                    ->label('Aksi'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageControlProduksis::route('/'),
            'atur-tugas' => AturTugasProduksi::route('/{record}/atur-tugas'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
