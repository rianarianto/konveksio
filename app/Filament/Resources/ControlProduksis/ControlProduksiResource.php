<?php

namespace App\Filament\Resources\ControlProduksis;

use App\Filament\Resources\ControlProduksis\Pages\ManageControlProduksis;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Actions\Action;
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
                        'custom' => '🧵 Custom (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Konveksi',
                    }),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->getStateUsing(
                        fn(OrderItem $record): int => OrderItem::where('order_id', $record->order_id)
                            ->where('product_name', $record->product_name)
                            ->where('design_status', 'approved')
                            ->sum('quantity')
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
                        'custom' => '🧵 Custom (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Produksi',
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
                        // Load tugas dan group berdasarkan urutan stage
                        $tasks = $record->productionTasks()
                            ->with(['assignedTo'])
                            ->get();

                        // Ambil semua stage yang ada beserta order_sequence-nya
                        $stageOrder = \App\Models\ProductionStage::pluck('order_sequence', 'name');

                        // Sort tugas berdasarkan order_sequence tahap, kemudian by id
                        $sortedTasks = $tasks->sortBy(function ($task) use ($stageOrder) {
                            return [$stageOrder[$task->stage_name] ?? 999, $task->id];
                        });

                        // Tentukan stage yang sedang "aktif" (bisa dimulai):
                        // Tahap pertama selalu bisa, tahap berikutnya hanya jika SEMUA tasks di tahap sebelumnya = done
                        $groupedByStage = $sortedTasks->groupBy('stage_name');
                        $stagesInOrder = $groupedByStage->keys()->sortBy(fn($s) => $stageOrder[$s] ?? 999);

                        $unlockedStages = [];
                        foreach ($stagesInOrder as $i => $stageName) {
                            if ($i === 0) {
                                $unlockedStages[] = $stageName;
                                continue;
                            }
                            // Stage ini bisa dibuka jika semua task di stage sebelumnya = done
                            $prevStage = $stagesInOrder[$i - 1];
                            $prevDone = $groupedByStage[$prevStage]->every(fn($t) => $t->status === 'done');
                            if ($prevDone) {
                                $unlockedStages[] = $stageName;
                            } else {
                                break; // Tahap berikutnya tetap terkunci
                            }
                        }

                        // Render HTML tabel tugas
                        $rows = '';
                        $prevStageName = null;
                        foreach ($sortedTasks as $task) {
                            $isUnlocked = in_array($task->stage_name, $unlockedStages);
                            $workerName = $task->assignedTo?->name ?? 'Tidak Diketahui';
                            $stageName = htmlspecialchars($task->stage_name);
                            $qty = $task->quantity . ' pcs';
                            $showStageLabel = $task->stage_name !== $prevStageName;
                            $prevStageName = $task->stage_name;

                            $statusBadge = match ($task->status) {
                                'pending' => '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">⏳ Antrian</span>',
                                'in_progress' => '<span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">🔨 Dikerjakan</span>',
                                'done' => '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">✅ Selesai</span>',
                                default => '',
                            };

                            if (!$isUnlocked) {
                                $actionBtn = '<span style="color:#9ca3af;font-size:12px">🔒 Menunggu tahap sebelumnya</span>';
                            } elseif ($task->status === 'pending') {
                                $actionBtn = '<a href="' . route('filament.admin.resources.control-produksis.task-action', ['task' => $task->id, 'action' => 'start', 'item' => $record->id]) . '" style="background:#2563eb;color:#fff;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">▶ Mulai</a>';
                            } elseif ($task->status === 'in_progress') {
                                $actionBtn = '<a href="' . route('filament.admin.resources.control-produksis.task-action', ['task' => $task->id, 'action' => 'done', 'item' => $record->id]) . '" style="background:#059669;color:#fff;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">✓ Tandai Selesai</a>';
                            } else {
                                $actionBtn = '<span style="color:#6b7280;font-size:12px">Selesai</span>';
                            }

                            $stageCell = $showStageLabel
                                ? '<td style="padding:10px 14px;font-weight:700;color:#374151;font-size:13px;border-bottom:1px solid #e5e7eb;">' . $stageName . '</td>'
                                : '<td style="padding:10px 14px;color:#9ca3af;font-size:13px;border-bottom:1px solid #e5e7eb;">↳</td>';

                            $rows .= '
                                <tr>
                                    ' . $stageCell . '
                                    <td style="padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($workerName) . '</td>
                                    <td style="padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;">' . $qty . '</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">' . $statusBadge . '</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $actionBtn . '</td>
                                </tr>';
                        }

                        // ─── Banner desain ───────────────────────────────────────
                        $designHtml = '';
                        if ($record->design_image) {
                            $designUrl = asset('storage/' . $record->design_image);
                            $designHtml = '
                                <div style="margin-bottom:16px;border:1.5px solid #c4b5fd;border-radius:12px;overflow:hidden;background:#faf5ff;">
                                    <div style="padding:8px 14px;background:#ede9fe;display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:16px;">🎨</span>
                                        <span style="font-size:13px;font-weight:600;color:#5b21b6;">Referensi Desain</span>
                                        <a href="' . $designUrl . '" target="_blank" style="margin-left:auto;font-size:11px;color:#7c3aed;text-decoration:underline;">Buka full ↗</a>
                                    </div>
                                    <div style="padding:12px;text-align:center;">
                                        <a href="' . $designUrl . '" target="_blank">
                                            <img src="' . $designUrl . '" style="max-height:200px;max-width:100%;object-fit:contain;border-radius:8px;cursor:zoom-in;" alt="Desain">
                                        </a>
                                    </div>
                                </div>';
                        } else {
                            $designHtml = '
                                <div style="margin-bottom:16px;border:1.5px dashed #d1d5db;border-radius:12px;padding:16px;text-align:center;background:#f9fafb;">
                                    <span style="font-size:13px;color:#9ca3af;">🖼️ Belum ada file desain yang diupload untuk item ini</span>
                                </div>';
                        }

                        $html = $designHtml . '
                            <div style="border-radius:10px;overflow-x:auto;border:1px solid #e5e7eb;-webkit-overflow-scrolling:touch;">
                                <table style="width:100%;min-width:600px;border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f9fafb;">
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">TAHAP</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">KARYAWAN</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">QTY</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">STATUS</th>
                                            <th style="padding:10px 14px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $rows . '</tbody>
                                </table>
                            </div>';

                        return [
                            Placeholder::make('task_table')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString($html)),
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
                    ->slideOver()
                    ->modalWidth('xl')
                    ->closeModalByClickingAway(false)
                    ->closeModalByEscaping(false)
                    ->modalHeading(fn(OrderItem $record) => $record->productionTasks()->exists() ? 'Edit Tugas Produksi' : 'Atur Tugas Produksi')
                    ->fillForm(function (OrderItem $record) {
                        $item = $record->load('productionTasks');
                        $tasksForRepeater = [];
                        foreach ($item->productionTasks as $task) {
                            $wagePerPcs = $task->quantity > 0 ? (int) ($task->wage_amount / $task->quantity) : 0;
                            $taskRow = [
                                'id' => $task->id,
                                'stage_name' => $task->stage_name,
                                'assigned_to' => $task->assigned_to,
                                'quantity' => $task->quantity,
                                'wage_per_pcs' => $wagePerPcs,
                                'description' => $task->description,
                            ];

                            // Unpack size_quantities into the flat row state
                            if (is_array($task->size_quantities)) {
                                $details = $item->size_and_request_details ?? [];
                                foreach ($task->size_quantities as $sz => $qty) {
                                    $szUpper = strtoupper($sz);
                                    $taskRow[$szUpper] = $qty;

                                    // Mapping for custom items (backward compatibility)
                                    if ($item->production_category === 'custom' && !empty($details['detail_custom'])) {
                                        foreach ($details['detail_custom'] as $index => $u) {
                                            $pName = trim($u['nama'] ?? 'Person ' . ($index + 1));
                                            $safeKey = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $pName)) . '_' . $index;
                                            if ($szUpper === strtoupper($pName) || $szUpper === $safeKey) {
                                                $taskRow[$safeKey] = $qty;
                                            }
                                        }
                                    }
                                }
                            }
                            $tasksForRepeater[(string) \Illuminate\Support\Str::uuid()] = $taskRow;
                        }
                        return [
                            'productionTasks' => $tasksForRepeater,
                        ];
                    })
                    ->form(function (OrderItem $record) {
                        $item = $record;

                        return [
                            \Filament\Schemas\Components\Section::make('Rincian Spesifikasi & Produksi')
                                ->description('Klik untuk melihat detail model, ukuran, bahan, dan catatan')
                                ->icon('heroicon-m-information-circle')
                                ->collapsed()
                                ->compact()
                                ->schema([
                                    Placeholder::make('technical_specs')
                                        ->hiddenLabel()
                                        ->content(function () use ($item): HtmlString {
                                            if (!$item)
                                                return new HtmlString('');

                                            $name = htmlspecialchars($item->product_name ?? 'Produk');
                                            $cat = $item->production_category ?? 'produksi';
                                            $details = $item->size_and_request_details ?? [];
                                            $bahan = $item->bahan;
                                            $allOrderItems = OrderItem::where('order_id', $item->order_id)->where('product_name', $item->product_name)->get();

                                            $catLabel = match ($cat) {
                                                'non_produksi' => 'BAJU JADI',
                                                'jasa' => 'JASA',
                                                default => 'KONVEKSI',
                                            };

                                            // STYLE CONSTANTS
                                            $primaryColor = '#7c3aed';
                                            $html = '<div style="font-family:inherit; color:#1f2937;">';

                                            // HEADER (Standard Title Style)
                                            $html .= '<div style="margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:12px;">';
                                            $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                            $html .= '<span style="font-size:20px; font-weight:800; letter-spacing:-0.01em;">' . strtoupper($name) . '</span>';
                                            $html .= '<small style="background:#f3e8ff; color:' . $primaryColor . '; font-weight:800; padding:2px 8px; border-radius:4px; font-size:10px; border:1px solid #ddd6fe;">' . $catLabel . '</small>';
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            // MATERIAL SECTION (Standard Color Swatch Style)
                                            $hex = $bahan?->color_code ?: '#e5e7eb';
                                            $bahanLabel = $bahan ? (($bahan->material->name ?? 'Bahan') . ' - ' . ($bahan->color_name ?? 'Tanpa Warna')) : ($details['bahan'] ?? '-');

                                            $html .= '<div style="margin-bottom:24px;">';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">INFORMASI BAHAN</div>';
                                            $html .= '<div style="display:flex; align-items:center; gap:12px; padding:12px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">';
                                            $html .= '<span style="width:16px; height:16px; border-radius:50%; background:' . $hex . '; border:1px solid rgba(0,0,0,0.15); flex-shrink:0;"></span>';
                                            $html .= '<div style="flex:1;">';
                                            $html .= '<div style="font-size:13px; font-weight:700; color:#111827;">' . htmlspecialchars($bahanLabel) . '</div>';

                                            // Sablon Info inside Material Info
                                            $sablon = $details['sablon_bordir'] ?? [];
                                            if (!empty($sablon)) {
                                                $sblnTexts = [];
                                                foreach ($sablon as $s) {
                                                    $sblnTexts[] = ($s['jenis'] ?? '') . ' (' . ($s['lokasi'] ?? '') . ')';
                                                }
                                                $html .= '<div style="font-size:11px; font-weight:600; color:' . $primaryColor . '; margin-top:2px;">SABLON/BORDIR: ' . htmlspecialchars(implode(', ', $sblnTexts)) . '</div>';
                                            }
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            // VARIATION SECTION
                                            $genders = [];
                                            foreach ($allOrderItems as $ai) {
                                                $idtl = $ai->size_and_request_details ?? [];
                                                $g = $idtl['gender'] ?? 'L';
                                                if (!isset($genders[$g]))
                                                    $genders[$g] = ['qty' => 0, 'models' => []];
                                                $genders[$g]['qty'] += $ai->quantity;

                                                $mParts = [];
                                                if (isset($idtl['sleeve_model']))
                                                    $mParts[] = 'Lengan ' . $idtl['sleeve_model'];
                                                if (isset($idtl['pocket_model']) && $idtl['pocket_model'] !== 'tanpa_saku')
                                                    $mParts[] = 'Saku ' . str_replace('_', ' ', $idtl['pocket_model']);
                                                if (!empty($idtl['is_tunic']))
                                                    $mParts[] = 'Tunik';
                                                $mKey = empty($mParts) ? 'Model Standar' : implode(', ', $mParts);

                                                if (!isset($genders[$g]['models'][$mKey])) {
                                                    $genders[$g]['models'][$mKey] = [
                                                        'qty' => 0,
                                                        'sizes' => [],
                                                        'notes' => [],
                                                        'custom' => [],
                                                        'attrs' => [
                                                            'LENGAN' => isset($idtl['sleeve_model']) ? strtoupper($idtl['sleeve_model']) : 'PENDEK',
                                                            'SAKU' => isset($idtl['pocket_model']) ? strtoupper(str_replace('_', ' ', $idtl['pocket_model'])) : 'TANPA SAKU',
                                                            'KANCING' => isset($idtl['button_model']) ? strtoupper($idtl['button_model']) : 'BIASA',
                                                        ]
                                                    ];
                                                }
                                                $genders[$g]['models'][$mKey]['qty'] += $ai->quantity;
                                                $sz = $ai->size ?? '-';
                                                $genders[$g]['models'][$mKey]['sizes'][$sz] = ($genders[$g]['models'][$mKey]['sizes'][$sz] ?? 0) + $ai->quantity;
                                                if (!empty($idtl['request_tambahan']))
                                                    $genders[$g]['models'][$mKey]['notes'][] = $idtl['request_tambahan'];

                                                // Custom measurements
                                                if ($ai->size === 'Custom' && $ai->recipient_name) {
                                                    $m = [];
                                                    foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                        if (!empty($idtl[$mk]))
                                                            $m[] = $mk . ':' . $idtl[$mk];
                                                    }
                                                    $genders[$g]['models'][$mKey]['custom'][] = $ai->recipient_name . (!empty($m) ? ' (' . implode(' ', $m) . ')' : '');
                                                }
                                            }

                                            $html .= '<div>';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">RINCIAN PRODUKSI</div>';
                                            $html .= '<div style="display:flex; flex-direction:column; gap:8px;">';

                                            $hasKonveksiItems = count($genders) > 0 && in_array($cat, ['produksi', 'custom']);

                                            if ($hasKonveksiItems) {
                                                foreach ($genders as $gCode => $gData) {
                                                    $gName = $gCode === 'P' ? 'PEREMPUAN' : 'LAKI-LAKI';
                                                    $html .= '<div x-data="{ open: true }" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:white;">';
                                                    $html .= '<div @click="open = !open" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; padding:10px 16px; background:#f9fafb;">';
                                                    $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                                    $html .= '<span style="font-size:12px; font-weight:800; color:#374151;">' . $gName . '</span>';
                                                    $html .= '<span style="font-size:12px; font-weight:900; color:' . $primaryColor . ';">' . $gData['qty'] . ' pcs</span>';
                                                    $html .= '</div>';
                                                    $html .= '<svg :class="open ? \'rotate-180\' : \'\'" style="width:14px; height:14px; transition:0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>';
                                                    $html .= '</div>';

                                                    $html .= '<div x-show="open" style="padding:16px; border-top:1px solid #f1f5f9; display:flex; flex-direction:column; gap:16px;">';
                                                    $hasMultipleModels = count($gData['models']) > 1;
                                                    foreach ($gData['models'] as $mKey => $mData) {
                                                        $html .= '<div>';

                                                        // Hanya tampilkan sub-header model jika ada lebih dari 1 varian
                                                        if ($hasMultipleModels) {
                                                            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:6px 10px; background:#f3e8ff; border-radius:6px;">';
                                                            $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . '; text-transform:uppercase;">' . $mKey . '</span>';
                                                            $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . ';">' . $mData['qty'] . ' pcs</span>';
                                                            $html .= '</div>';
                                                        }

                                                        // Attributes (Compact style)
                                                        $atxt = [];
                                                        foreach ($mData['attrs'] as $ak => $av) {
                                                            $atxt[] = '<span style="color:#9ca3af; font-size:10px;">' . $ak . ':</span><span style="color:#4b5563; margin-left:2px;">' . $av . '</span>';
                                                        }
                                                        $html .= '<div style="display:flex; gap:12px; font-size:11px; font-weight:700; margin-bottom:8px;">' . implode('<span style="color:#e5e7eb;">|</span>', $atxt) . '</div>';

                                                        // Sizes (Pill style)
                                                        $stxt = [];
                                                        foreach ($mData['sizes'] as $sz => $sqty) {
                                                            $stxt[] = '<div style="padding:4px 8px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:4px; font-size:12px; font-weight:800; color:#1e293b;">' . $sz . ': <span style="color:' . $primaryColor . ';">' . $sqty . '</span></div>';
                                                        }
                                                        $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . implode('', $stxt) . '</div>';

                                                        // Custom Sizes Details
                                                        if (!empty($mData['custom'])) {
                                                            $html .= '<div style="margin-top:8px; padding:8px 12px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px;">';
                                                            $html .= '<div style="font-size:10px; font-weight:800; color:#6b21a8; text-transform:uppercase; margin-bottom:4px;">UKURAN CUSTOM:</div>';
                                                            foreach ($mData['custom'] as $c) {
                                                                $html .= '<div style="font-size:12px; font-weight:700; color:#581c87;">• ' . htmlspecialchars($c) . '</div>';
                                                            }
                                                            $html .= '</div>';
                                                        }

                                                        // Notes
                                                        if (!empty($mData['notes'])) {
                                                            $html .= '<div style="margin-top:8px; padding:8px 12px; background:#fffcf0; border:1px solid #fef3c7; border-radius:6px;">';
                                                            $html .= '<div style="font-size:10px; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:4px;">CATATAN:</div>';
                                                            foreach ($mData['notes'] as $n) {
                                                                $html .= '<div style="font-size:12px; font-weight:700; color:#92400e;">- ' . htmlspecialchars($n) . '</div>';
                                                            }
                                                            $html .= '</div>';
                                                        }
                                                        $html .= '</div>';
                                                        if ($hasMultipleModels && next($gData['models']))
                                                            $html .= '<hr style="border:none; border-top:1px dashed #e2e8f0; margin:4px 0;">';
                                                    }
                                                    $html .= '</div></div>';
                                                }
                                            } else {
                                                $html .= '<div style="padding:16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; text-align:center; font-size:12px; color:#6b7280; font-weight:600;">Tidak ada rincian produksi untuk varian ini.</div>';
                                            }
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            // Action Link
                                            try {
                                                $url = route('filament.admin.resources.orders.edit', ['tenant' => filament()->getTenant()->id, 'record' => $item->order_id]);
                                            } catch (\Exception $e) {
                                                $url = '#';
                                            }
                                            $html .= '<div style="margin-top:24px;">';
                                            $html .= '<a href="' . $url . '" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:10px; background:#ffffff; color:#374151; border:1px solid #d1d5db; border-radius:6px; text-decoration:none; font-size:11px; font-weight:800; transition:0.1s;">';
                                            $html .= '<svg style="width:14px; height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>';
                                            $html .= 'LIHAT DETAIL PESANAN';
                                            $html .= '</a>';
                                            $html .= '</div>';

                                            $html .= '</div>';
                                            return new HtmlString($html);
                                        })
                                        ->columnSpanFull(),
                                ]),

                            Placeholder::make('browser_guard')
                                ->hiddenLabel()
                                ->content(new HtmlString('
                                    <script>
                                        if(!window.isBrowserGuardSet) {
                                            window.addEventListener("beforeunload", (e) => {
                                                if (document.querySelector(".fi-modal-window")) {
                                                    e.preventDefault();
                                                    e.returnValue = "";
                                                }
                                            });
                                            window.isBrowserGuardSet = true;
                                        }
                                    </script>
                                ')),


                            Placeholder::make('custom_repeater_styling')
                                ->hiddenLabel()
                                ->content(new HtmlString('
                                    <style>
                                        .fi-fo-repeater-item {
                                            background-color: #f5f3ff !important;
                                            border: 1.5px solid #ddd6fe !important;
                                            border-radius: 12px !important;
                                            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                                        }
                                        .fi-fo-repeater-item-header {
                                            border-bottom: 1px solid #ddd6fe !important;
                                            background-color: #ede9fe !important;
                                            border-top-left-radius: 12px !important;
                                            border-top-right-radius: 12px !important;
                                        }
                                    </style>
                                ')),

                            \Filament\Forms\Components\Repeater::make('productionTasks')
                                ->label('Daftar Tugas Produksi')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Hidden::make('id'),
                                    \Filament\Schemas\Components\Grid::make(2)
                                        ->schema([
                                            \Filament\Forms\Components\Select::make('stage_name')
                                                ->label('Tahap Pekerjaan')
                                                ->extraAttributes(['style' => 'background-color: #ffffff; border-radius: 8px; padding: 2px; border: 1px solid #ddd6fe;'])
                                                ->options(function () use ($item) {
                                                    $category = $item->production_category ?? 'produksi';

                                                    $query = ProductionStage::query()->orderBy('order_sequence');
                                                    if ($category === 'produksi') {
                                                        $query->where('for_produksi_custom', true);
                                                    } elseif ($category === 'custom') {
                                                        $query->where('for_produksi_custom', true);
                                                    } elseif ($category === 'non_produksi') {
                                                        $query->where('for_non_produksi', true);
                                                    } elseif ($category === 'jasa') {
                                                        $query->where('for_jasa', true);
                                                    }

                                                    return $query->pluck('name', 'name');
                                                })
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    if ($state) {
                                                        $stage = \App\Models\ProductionStage::where('name', $state)->first();
                                                        if ($stage) {
                                                            $set('wage_per_pcs', $stage->base_wage);
                                                        }
                                                    }
                                                }),

                                            \Filament\Forms\Components\Select::make('assigned_to')
                                                ->label('Tugaskan Ke (Karyawan)')
                                                ->extraAttributes(['style' => 'background-color: #ffffff; border-radius: 8px; padding: 2px; border: 1px solid #ddd6fe;'])
                                                ->options(function () {
                                                    $workers = \App\Models\Worker::where('shop_id', \Filament\Facades\Filament::getTenant()->id)
                                                        ->where('is_active', true)
                                                        ->get();

                                                    $opts = [];
                                                    foreach ($workers as $w) {
                                                        $q = $w->active_queue_count;
                                                        $opts[$w->id] = "{$w->name}" . ($q > 0 ? " — (Antrian: {$q} pcs)" : "");
                                                    }
                                                    return $opts;
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required(),

                                            TextInput::make('wage_per_pcs')
                                                ->label('Upah Satuan Dasar (Rp)')
                                                ->numeric()
                                                ->placeholder('0')
                                                ->prefix('Rp')
                                                ->extraInputAttributes(['style' => 'background-color: #ffffff; border-color: #ddd6fe; font-weight: 600;'])
                                                ->helperText('Otomatis/Bisa diubah'),

                                            TextInput::make('quantity')
                                                ->label('Total Qty (otomatis)')
                                                ->numeric()
                                                ->readOnly()
                                                ->dehydrated()
                                                ->placeholder('0')
                                                ->extraInputAttributes(['style' => 'font-weight:700;color:#7c3aed;cursor:not-allowed;'])
                                                ->helperText('Dihitung otomatis'),
                                        ]),

                                    \Filament\Schemas\Components\Fieldset::make(null)
                                        ->label('Detail Qty per Ukuran')
                                        ->extraAttributes(['style' => 'border-color: #ddd6fe;'])
                                        ->schema(function (\App\Models\OrderItem $record) use ($item) {
                                            $fields = [];
                                            $cat = $record->production_category ?? 'produksi';
                                            $details = $record->size_and_request_details ?? [];

                                            // Recalculate Total Qty from all size inputs
                                            $recalcQty = function (Get $get, Set $set) {
                                                $excludeKeys = ['id', 'stage_name', 'assigned_to', 'wage_per_pcs', 'quantity', 'description', '_fill_all', 'qty'];
                                                $total = 0;
                                                // Because Fieldset flattens the schema, $get('') from a sub-field or using a key gets the flattened array of the Repeater row
                                                // So we must manually check all keys in the current repeater item's state
                                                // Filament's $get('../') from within the fieldset usually just gives the repeater row if we use specific keys
                                                // However, since we define fields dynamically, they are at the ROOT of the repeater item.
                                                // We can grab the whole item state by getting relative path ''
                                                $state = $get('');
                                                if (is_array($state)) {
                                                    foreach ($state as $k => $v) {
                                                        if (!in_array($k, $excludeKeys) && is_numeric($v)) {
                                                            $total += (int) $v;
                                                        }
                                                    }
                                                }
                                                $set('quantity', $total > 0 ? $total : 0);
                                            };

                                            // Ekstrak ukuran + stok dari SEMUA items dalam grup produk
                                            $extractSizes = function () use ($item, $details): array {
                                                // Aggregate from all items in the product group
                                                $allGroupItems = OrderItem::where('order_id', $item->order_id)
                                                    ->where('product_name', $item->product_name)
                                                    ->where('design_status', 'approved')
                                                    ->get();

                                                $sizes = [];
                                                foreach ($allGroupItems as $gi) {
                                                    $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                                                    $sizes[$sz] = ($sizes[$sz] ?? 0) + $gi->quantity;
                                                }

                                                // Fallback: try old format from details
                                                if (empty($sizes)) {
                                                    if (isset($details['sizes']) && is_array($details['sizes'])) {
                                                        foreach ($details['sizes'] as $sz => $qty) {
                                                            if ((int) $qty > 0)
                                                                $sizes[strtoupper($sz)] = (int) $qty;
                                                        }
                                                    } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                                        foreach ($details['varian_ukuran'] as $v) {
                                                            $sz = strtoupper($v['ukuran'] ?? '');
                                                            if ($sz && (int) ($v['qty'] ?? 0) > 0) {
                                                                $sizes[$sz] = (int) $v['qty'];
                                                            }
                                                        }
                                                    }
                                                }
                                                return $sizes;
                                            };

                                            if ($cat === 'custom') {
                                                $people = [];
                                                if (!empty($details['detail_custom']) && is_array($details['detail_custom'])) {
                                                    foreach ($details['detail_custom'] as $index => $u) {
                                                        $person = trim($u['nama'] ?? 'Person ' . ($index + 1));
                                                        $sz = strtoupper($u['ukuran'] ?? 'CUSTOM');
                                                        $safeKey = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $person) . '_' . $index);
                                                        $people[] = [
                                                            'key' => $safeKey,
                                                            'label' => $person,
                                                            'sz' => $sz
                                                        ];
                                                    }
                                                }

                                                // Toggle Kerjakan Semua (custom)
                                                $fields[] = \Filament\Forms\Components\Toggle::make('_fill_all')
                                                    ->label('✓ Kerjakan semua (' . count($people) . ' orang)')
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($people, $recalcQty) {
                                                    foreach ($people as $p) {
                                                        $set($p['key'], $state ? 1 : 0);
                                                    }
                                                    $recalcQty($get, $set);
                                                })
                                                    ->columnSpanFull();

                                                foreach ($people as $p) {
                                                    $fields[] = \Filament\Forms\Components\TextInput::make($p['key'])
                                                        ->label($p['label'])
                                                        ->placeholder($p['sz'])
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(1)
                                                        ->placeholder('0')
                                                        ->readOnly(fn(Get $get) => (bool) $get('_fill_all'))
                                                        ->extraInputAttributes(fn(Get $get) => array_merge(
                                                            [
                                                                'style' => 'text-align:center;padding-left:0.5rem;padding-right:0.5rem;',
                                                                'oninput' => "if(this.value > 1) this.value = 1;"
                                                            ],
                                                            $get('_fill_all') ? ['style' => 'background-color:rgba(175,175,175,0.08);cursor:not-allowed;text-align:center;padding-left:0.5rem;padding-right:0.5rem;'] : []
                                                        ))
                                                        ->live(debounce: 300)
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty, $p) {
                                                            if ((int) $state > 1) {
                                                                $set($p['key'], 1);
                                                            }
                                                            $recalcQty($get, $set);
                                                        });
                                                }
                                            } else {
                                                $sizes = $extractSizes();

                                                // Map request tambahan per ukuran
                                                $requestPerSize = [];
                                                $reqData = $details['request_tambahan'] ?? [];
                                                if (is_array($reqData)) {
                                                    foreach ($reqData as $req) {
                                                        $jenis = $req['jenis'] ?? null;
                                                        $ukuran = strtoupper($req['ukuran'] ?? '');
                                                        $qty = (int) ($req['qty_tambahan'] ?? 0);
                                                        if (!$jenis || $qty <= 0)
                                                            continue;
                                                        if ($ukuran === '__SEMUA__' || $ukuran === '') {
                                                            foreach (array_keys($sizes) as $sz) {
                                                                $requestPerSize[$sz][] = "{$jenis} ({$qty})";
                                                            }
                                                        } else {
                                                            $requestPerSize[$ukuran][] = "{$jenis} ({$qty})";
                                                        }
                                                    }
                                                }

                                                if (!empty($sizes)) {
                                                    $totalStok = array_sum($sizes);
                                                    $sizeDetails = [];
                                                    foreach ($sizes as $sz => $qty) {
                                                        $sizeDetails[] = "{$sz}: {$qty}";
                                                    }
                                                    $sizeStr = implode(', ', $sizeDetails);

                                                    // Toggle Kerjakan Semua
                                                    $fields[] = \Filament\Forms\Components\Toggle::make('_fill_all')
                                                        ->label('✓ Kerjakan semua ukuran (' . $sizeStr . ' | total: ' . $totalStok . ' pcs)')
                                                        ->dehydrated(false)
                                                        ->live()
                                                        ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($sizes, $recalcQty) {
                                                        foreach ($sizes as $sizeName => $maxQty) {
                                                            $set($sizeName, $state ? $maxQty : 0);
                                                        }
                                                        $recalcQty($get, $set);
                                                    })
                                                        ->columnSpanFull();

                                                    foreach ($sizes as $sizeName => $maxQty) {
                                                        $fields[] = \Filament\Forms\Components\TextInput::make($sizeName)
                                                            ->label($sizeName)
                                                            ->placeholder("0 - {$maxQty}")
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue($maxQty)
                                                            ->placeholder('0')
                                                            ->readOnly(fn(Get $get) => (bool) $get('_fill_all'))
                                                            ->extraInputAttributes(fn(Get $get) => array_merge(
                                                                [
                                                                    'style' => 'text-align:center;padding-left:0.5rem;padding-right:0.5rem;background-color:#ffffff;border-color:#ddd6fe;font-weight:600;',
                                                                    'oninput' => "if(this.value > {$maxQty}) this.value = {$maxQty};"
                                                                ],
                                                                $get('_fill_all') ? ['style' => 'background-color:rgba(175,175,175,0.08);cursor:not-allowed;text-align:center;padding-left:0.5rem;padding-right:0.5rem;'] : []
                                                            ))
                                                            ->live(debounce: 300)
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty, $maxQty, $sizeName) {
                                                                if ((int) $state > $maxQty) {
                                                                    $set($sizeName, $maxQty);
                                                                }
                                                                $recalcQty($get, $set);
                                                            });
                                                    }

                                                    // Info request tambahan — hanya muncul jika ada & stage_name mengandung 'jahit'
                                                    if (!empty($requestPerSize)) {
                                                        $summaryParts = [];
                                                        foreach ($requestPerSize as $sz => $reqs) {
                                                            $summaryParts[] = strtoupper($sz) . ' -> ' . implode(', ', $reqs);
                                                        }
                                                        $summaryText = '* Request Tambahan:  ' . implode('   |   ', $summaryParts);
                                                    }
                                                } else {
                                                    // Fallback: tidak ada data ukuran
                                                    $fields[] = \Filament\Forms\Components\TextInput::make('qty')
                                                        ->label('Target Qty')
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->placeholder('0')
                                                        ->live(debounce: 300)
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty) {
                                                        $recalcQty($get, $set);
                                                    });
                                                }
                                            }

                                            return $fields;
                                        })
                                        ->columns(6)
                                        ->columnSpanFull(),

                                    \Filament\Forms\Components\Textarea::make('description')
                                        ->label('Catatan Instruksi')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull()
                                ->itemLabel(fn(array $state): ?string => $state['stage_name'] ?? null)
                                ->addActionLabel('+ Tambah Tugas Baru')
                                ->addAction(fn($action) => $action->color('primary')),
                        ];
                    })
                    ->action(function (array $data, OrderItem $record, \Filament\Actions\Action $action) {
                        $item = $record;
                        $category = $item->production_category ?? 'produksi';
                        $details = $item->size_and_request_details ?? [];

                        $tasksData = $data['productionTasks'] ?? [];

                        // ══════════════════════════════════════════════════════════════
                        // PERSIAPAN DATA KAPASITAS ASLI
                        // ══════════════════════════════════════════════════════════════
                        $originalSizes = []; // ['S' => 20, 'M' => 30, ...]
                        // Aggregate from all items in the product group
                        $allGroupItems = OrderItem::where('order_id', $item->order_id)
                            ->where('product_name', $item->product_name)
                            ->where('design_status', 'approved')
                            ->get();

                        foreach ($allGroupItems as $gi) {
                            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                            if ($sz !== 'CUSTOM') {
                                $originalSizes[$sz] = ($originalSizes[$sz] ?? 0) + $gi->quantity;
                            } else {
                                // Custom: use recipient name as key
                                $pName = strtoupper($gi->recipient_name ?? 'CUSTOM_' . $gi->id);
                                $originalSizes[$pName] = ($originalSizes[$pName] ?? 0) + $gi->quantity;
                            }
                        }

                        // Fallback: try old format
                        if (empty($originalSizes)) {
                            if (isset($details['sizes']) && is_array($details['sizes'])) {
                                foreach ($details['sizes'] as $sz => $qty) {
                                    if ((int) $qty > 0)
                                        $originalSizes[strtoupper($sz)] = (int) $qty;
                                }
                            } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                foreach ($details['varian_ukuran'] as $v) {
                                    $sz = strtoupper($v['ukuran'] ?? '');
                                    if ($sz && (int) ($v['qty'] ?? 0) > 0)
                                        $originalSizes[$sz] = (int) $v['qty'];
                                }
                            } elseif ($category === 'custom' && !empty($details['detail_custom'])) {
                                foreach ($details['detail_custom'] as $index => $u) {
                                    $person = strtoupper($u['nama'] ?? 'Person ' . ($index + 1));
                                    $originalSizes[$person] = 1;
                                }
                            }
                        }
                        $totalOrderQty = $allGroupItems->sum('quantity');

                        // ══════════════════════════════════════════════════════════════
                        // AGREGASI: Qty & stage dari semua baris tugas
                        // ══════════════════════════════════════════════════════════════
                        $usedQtyPerStageSize = []; // ['Jahit']['M'] = 50
                        $usedQtyPerStage = []; // ['Jahit'] = 100
                        $assignedStages = []; // ['Jahit', 'Potong']
            
                        foreach ($tasksData as $idx => $taskItem) {
                            $stage = $taskItem['stage_name'] ?? null;

                            $calculatedQty = 0;
                            $sqs = [];
                            $excludeKeys = ['id', 'stage_name', 'assigned_to', 'wage_per_pcs', 'quantity', 'description', '_fill_all', 'qty'];
                            foreach ($taskItem as $k => $v) {
                                if (!in_array($k, $excludeKeys) && is_numeric($v) && (int) $v > 0) {
                                    $calculatedQty += (int) $v;
                                    $sqs[$k] = (int) $v;
                                }
                            }
                            if ($calculatedQty === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) {
                                $calculatedQty = (int) $taskItem['qty'];
                            }
                            if ($calculatedQty === 0 && isset($taskItem['quantity']) && (int) $taskItem['quantity'] > 0) {
                                $calculatedQty = (int) $taskItem['quantity'];
                            }

                            $qty = $calculatedQty;

                            // ─── Cek #1: Setiap baris wajib punya qty > 0 ─────────────
                            if ($qty === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Ada Tugas Tanpa Qty!')
                                    ->body("Baris tugas <strong>{$stage}</strong> tidak memiliki qty yang diisi. Isi qty atau hapus baris tersebut.")
                                    ->danger()->send();
                                $action->halt();
                                return;
                            }

                            if ($stage) {
                                $assignedStages[] = $stage;
                                $usedQtyPerStage[$stage] = ($usedQtyPerStage[$stage] ?? 0) + $qty;

                                foreach ($sqs as $key => $val) {
                                    $upperKey = strtoupper($key);
                                    $usedQtyPerStageSize[$stage][$upperKey] = ($usedQtyPerStageSize[$stage][$upperKey] ?? 0) + (int) $val;
                                }
                            }
                        }

                        // ─── Cek #2: Qty per ukuran per tahap tidak boleh melebihi kapasitas asli ──
                        if (!empty($originalSizes)) {
                            $errors = [];
                            foreach ($usedQtyPerStageSize as $stageName => $sizeUsage) {
                                foreach ($sizeUsage as $sz => $usedQty) {
                                    $maxQty = $originalSizes[$sz] ?? null;
                                    if ($maxQty !== null && $usedQty > $maxQty) {
                                        $errors[] = "Tahap <strong>{$stageName}</strong> — Ukuran <strong>{$sz}</strong>: ditugaskan {$usedQty} pcs, maks {$maxQty} pcs.";
                                    }
                                }
                            }
                            if (!empty($errors)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('❌ Qty Per Ukuran Melebihi Kapasitas!')
                                    ->body(new \Illuminate\Support\HtmlString(implode('<br>', $errors)))
                                    ->danger()->send();
                                $action->halt();
                                return;
                            }
                        }

                        // ─── Cek #3: Total Qty per tahap HARUS SAMA dengan Total Pesanan ──
                        if (empty($usedQtyPerStage)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Belum Ada Tugas!')
                                ->body('Anda belum menambahkan tahapan produksi apapun.')
                                ->danger()->send();
                            $action->halt();
                            return;
                        }

                        $mismatchErrors = [];
                        foreach ($usedQtyPerStage as $stageName => $totalUsed) {
                            if ($totalUsed !== $totalOrderQty) {
                                $diff = $totalOrderQty - $totalUsed;
                                if ($diff > 0) {
                                    $mismatchErrors[] = "Tahap <strong>{$stageName}</strong>: baru diatur {$totalUsed} pcs, <strong>kurang {$diff} pcs</strong> lagi!";
                                } else {
                                    $excess = abs($diff);
                                    $mismatchErrors[] = "Tahap <strong>{$stageName}</strong>: diatur {$totalUsed} pcs, <strong>kelebihan {$excess} pcs</strong>!";
                                }
                            }
                        }

                        if (!empty($mismatchErrors)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Jumlah Tugas Tidak Sesuai Pesanan!')
                                ->body(new \Illuminate\Support\HtmlString("Total pesanan seharusnya <strong>{$totalOrderQty} pcs</strong>.<br><br>" . implode('<br>', $mismatchErrors)))
                                ->danger()->send();
                            $action->halt();
                            return;
                        }

                        // ─── Cek #3: Total qty per tahap tidak boleh melebihi total order ────────
                        $stageOverErrors = [];
                        foreach ($usedQtyPerStage as $stageName => $totalAssigned) {
                            if ($totalAssigned > $totalOrderQty) {
                                $stageOverErrors[] = "Tahap <strong>{$stageName}</strong>: total ditugaskan {$totalAssigned} pcs, maks {$totalOrderQty} pcs.";
                            }
                        }
                        if (!empty($stageOverErrors)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Total Qty Melebihi Total Order!')
                                ->body(new \Illuminate\Support\HtmlString(implode('<br>', $stageOverErrors)))
                                ->danger()->send();
                            $action->halt();
                            return;
                        }

                        // ─── Cek #4 (Warning): Total qty per tahap kurang dari total order ────────
                        $underAssignedWarnings = [];
                        foreach ($usedQtyPerStage as $stageName => $totalAssigned) {
                            if ($totalAssigned < $totalOrderQty) {
                                $kekurangan = $totalOrderQty - $totalAssigned;
                                $underAssignedWarnings[] = "Tahap <strong>{$stageName}</strong>: baru {$totalAssigned} dari {$totalOrderQty} pcs (kurang {$kekurangan} pcs).";
                            }
                        }
                        if (!empty($underAssignedWarnings)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Qty Belum Lengkap')
                                ->body(new \Illuminate\Support\HtmlString(
                                    'Total penugasan belum mencapai total order:<br>' . implode('<br>', $underAssignedWarnings)
                                ))
                                ->danger()->persistent()->send();
                            $action->halt();
                            return;
                        }

                        // ─── Cek #5 (Warning): Pastikan tahapan yang diperlukan sudah ada ─────────
                        $requiredStages = ProductionStage::query()->orderBy('order_sequence');
                        if ($category === 'produksi' || $category === 'custom') {
                            $requiredStages->where('for_produksi_custom', true);
                        } elseif ($category === 'non_produksi') {
                            $requiredStages->where('for_non_produksi', true);
                        } elseif ($category === 'jasa') {
                            $requiredStages->where('for_jasa', true);
                        }
                        $requiredStageNames = $requiredStages->pluck('name')->toArray();
                        $missingStages = array_diff($requiredStageNames, array_unique($assignedStages));
                        if (!empty($missingStages)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Tahapan Belum Lengkap')
                                ->body(new \Illuminate\Support\HtmlString(
                                    'Penyimpanan dibatalkan, tahapan berikut wajib di-assign:<br><strong>' . implode(', ', $missingStages) . '</strong>'
                                ))
                                ->danger()->persistent()->send();
                            $action->halt();
                            return;
                        }
                        // ══════════════════════════════════════════════════════════════
            
                        $existingTaskIds = [];

                        foreach ($tasksData as $taskItem) {
                            // Extract size_quantities which are dynamically generated inputs flattened by Fieldset
                            $sizeQuantities = [];

                            // The keys we want to grab depend on the aggregated originalSizes (already computed)
                            $sizesToLookFor = array_keys($originalSizes);

                            // Extract these specific keys from $taskItem if they exist and are greater than 0
                            foreach ($sizesToLookFor as $key) {
                                if (isset($taskItem[$key]) && (int) $taskItem[$key] > 0) {
                                    $sizeQuantities[$key] = (int) $taskItem[$key];
                                }
                            }

                            // Hitung quantity dari sizeQuantities yang sudah diekstrak (quantity field readOnly jadi tidak reliable)
                            $quantity = array_sum($sizeQuantities);
                            // Fallback ke field qty biasa (untuk item tanpa ukuran)
                            if ($quantity === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) {
                                $quantity = (int) $taskItem['qty'];
                            }
                            $wagePerPcs = (float) ($taskItem['wage_per_pcs'] ?? 0);

                            $taskData = [
                                'stage_name' => $taskItem['stage_name'],
                                'assigned_to' => $taskItem['assigned_to'],
                                'quantity' => $quantity,
                                'wage_amount' => $quantity * $wagePerPcs,
                                'size_quantities' => empty($sizeQuantities) ? null : $sizeQuantities,
                                'description' => $taskItem['description'] ?? null,
                                'shop_id' => \Filament\Facades\Filament::getTenant()->id,
                            ];

                            if (!empty($taskItem['id'])) {
                                $task = \App\Models\ProductionTask::find($taskItem['id']);
                                if ($task) {
                                    $task->update($taskData);
                                    $existingTaskIds[] = $task->id;
                                }
                            } else {
                                $taskData['assigned_by'] = auth()->id();
                                $taskData['status'] = 'pending';
                                $newTask = $item->productionTasks()->create($taskData);
                                $existingTaskIds[] = $newTask->id;
                            }
                        }

                        $item->productionTasks()->whereNotIn('id', $existingTaskIds)->delete();

                        // ── Sinkronisasi status Order ──────────────────────────────
                        $item->refresh();
                        $order = $item->order;

                        if ($order) {
                            $totalTasks = $order->orderItems()
                                ->withCount('productionTasks')
                                ->get()
                                ->sum('production_tasks_count');

                            if ($totalTasks > 0 && in_array($order->status, ['diterima'])) {
                                $order->update(['status' => 'antrian']);
                            }
                        }

                        // Emit success notification!
                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil Diatur')
                            ->body('Tugas produksi berhasil diteapkan dan disimpan.')
                            ->success()
                            ->send();
                    })
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageControlProduksis::route('/'),
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
